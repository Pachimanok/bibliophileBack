<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityController extends Controller
{
    /**
     * List communities the authenticated user belongs to (accepted).
     */
    public function index()
    {
        $communities = Auth::user()->acceptedCommunities()
            ->withCount(['acceptedMembers'])
            ->with('owner:id,name,avatar')
            ->get();

        return response()->json($communities);
    }

    /**
     * List public communities (for discovery/search).
     */
    public function publicIndex(Request $request)
    {
        $query = Community::where('is_public', true)
            ->withCount('acceptedMembers')
            ->with('owner:id,name,avatar');

        if ($request->q) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Create a new community. Creator becomes owner and accepted member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_public'   => 'boolean',
        ]);

        $validated['owner_id'] = Auth::id();

        $community = Community::create($validated);

        // Add creator as accepted owner member
        $community->members()->attach(Auth::id(), [
            'role'      => 'owner',
            'status'    => 'accepted',
            'joined_at' => now(),
        ]);

        return response()->json($community->load('owner:id,name,avatar'), 201);
    }

    /**
     * Show community details with members and pending join requests.
     */
    public function show(Community $community)
    {
        $this->authorizeMember($community);

        $community->load([
            'owner:id,name,avatar',
            'acceptedMembers:id,name,avatar,email',
            'pendingMembers:id,name,avatar,email',
        ]);

        // Attach pivot data
        $community->loadCount('acceptedMembers');

        return response()->json($community);
    }

    /**
     * Update community settings (owner only).
     */
    public function update(Request $request, Community $community)
    {
        $this->authorizeOwner($community);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_public'   => 'boolean',
        ]);

        $community->update($validated);

        return response()->json($community->fresh()->load('owner:id,name,avatar'));
    }

    /**
     * Regenerate the invite code (owner only).
     */
    public function regenerateCode(Community $community)
    {
        $this->authorizeOwner($community);

        $community->invite_code = strtoupper(\Illuminate\Support\Str::random(8));
        $community->save();

        return response()->json(['invite_code' => $community->invite_code]);
    }

    /**
     * Join a community:
     *  - By invite code → accepted immediately (any visibility).
     *  - Public community without code → pending (awaits owner approval).
     */
    public function join(Request $request)
    {
        $request->validate([
            'invite_code' => 'nullable|string',
            'community_id' => 'nullable|integer|exists:communities,id',
        ]);

        $userId = Auth::id();

        if ($request->invite_code) {
            $community = Community::where('invite_code', strtoupper($request->invite_code))->firstOrFail();
        } elseif ($request->community_id) {
            $community = Community::findOrFail($request->community_id);
            if (!$community->is_public) {
                return response()->json(['message' => 'Esta comunidad es privada. Necesitás un código de invitación.'], 403);
            }
        } else {
            return response()->json(['message' => 'Debés proveer un código o ID de comunidad.'], 422);
        }

        // Already a member?
        if ($community->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Ya eres miembro de esta comunidad.'], 409);
        }

        $status = $request->invite_code ? 'accepted' : ($community->is_public ? 'pending' : 'accepted');

        $community->members()->attach($userId, [
            'role'      => 'member',
            'status'    => $status,
            'joined_at' => $status === 'accepted' ? now() : null,
        ]);

        $message = $status === 'pending'
            ? 'Solicitud enviada. Esperando aprobación del administrador.'
            : 'Te uniste a la comunidad correctamente.';

        return response()->json([
            'message'   => $message,
            'status'    => $status,
            'community' => $community->load('owner:id,name,avatar'),
        ]);
    }

    /**
     * Approve a pending join request (owner only).
     */
    public function approveMember(Request $request, Community $community)
    {
        $this->authorizeOwner($community);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $community->members()->updateExistingPivot($request->user_id, [
            'status'    => 'accepted',
            'joined_at' => now(),
        ]);

        return response()->json(['message' => 'Miembro aprobado.']);
    }

    /**
     * Reject (remove) a pending join request (owner only).
     */
    public function rejectMember(Request $request, Community $community)
    {
        $this->authorizeOwner($community);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $community->members()->detach($request->user_id);

        return response()->json(['message' => 'Solicitud rechazada.']);
    }

    /**
     * Leave a community (any accepted member).
     */
    public function leave(Community $community)
    {
        $this->authorizeMember($community);

        if ($community->owner_id === Auth::id()) {
            return response()->json(['message' => 'El dueño no puede abandonar la comunidad. Eliminala primero.'], 403);
        }

        $community->members()->detach(Auth::id());

        return response()->json(['message' => 'Saliste de la comunidad.']);
    }

    /**
     * Delete a community (owner only).
     */
    public function destroy(Community $community)
    {
        $this->authorizeOwner($community);

        $community->delete();

        return response()->json(['message' => 'Comunidad eliminada.']);
    }

    /**
     * Get the shared library of a community (physical/both books of accepted members).
     */
    public function library(Request $request, Community $community)
    {
        $this->authorizeMember($community);

        $query = $community->books();

        if ($request->q) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhereHas('author', fn($a) => $a->where('name', 'like', $term));
            });
        }

        $books = $query->get();

        return response()->json($books);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function authorizeMember(Community $community): void
    {
        $isMember = $community->acceptedMembers()
            ->where('users.id', Auth::id())
            ->exists();

        if (!$isMember) {
            abort(403, 'No eres miembro de esta comunidad.');
        }
    }

    private function authorizeOwner(Community $community): void
    {
        if ($community->owner_id !== Auth::id()) {
            abort(403, 'Solo el dueño puede realizar esta acción.');
        }
    }
}
