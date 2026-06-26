<?php

namespace App\Http\Controllers;

use App\Models\BookRequest;
use App\Models\Book;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookRequestController extends Controller
{
    /**
     * Requests I have SENT.
     */
    public function index()
    {
        $requests = BookRequest::where('requester_id', Auth::id())
            ->with('book.user:id,name,avatar', 'book.author', 'community:id,name')
            ->latest()
            ->get();

        return response()->json($requests);
    }

    /**
     * Requests RECEIVED (for books I own).
     */
    public function incoming()
    {
        $myBookIds = Auth::user()->books()->pluck('id');

        $requests = BookRequest::whereIn('book_id', $myBookIds)
            ->with('requester:id,name,avatar', 'book:id,title,cover_image,author_id', 'book.author:id,name', 'community:id,name')
            ->latest()
            ->get();

        return response()->json($requests);
    }

    /**
     * Create a new book request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'community_id' => 'required|exists:communities,id',
            'book_id'      => 'required|exists:books,id',
            'message'      => 'nullable|string|max:500',
        ]);

        // Can't request your own book
        $book = Book::findOrFail($validated['book_id']);
        if ($book->user_id === Auth::id()) {
            return response()->json(['message' => 'No podés pedir prestado tu propio libro.'], 422);
        }

        // Must be a member of the community
        $community = Community::findOrFail($validated['community_id']);
        $isMember = $community->acceptedMembers()->where('users.id', Auth::id())->exists();
        if (!$isMember) {
            return response()->json(['message' => 'No eres miembro de esta comunidad.'], 403);
        }

        // Avoid duplicate pending request
        $exists = BookRequest::where('requester_id', Auth::id())
            ->where('book_id', $validated['book_id'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ya tenés una solicitud pendiente para este libro.'], 409);
        }

        $validated['requester_id'] = Auth::id();

        $bookRequest = BookRequest::create($validated);

        return response()->json($bookRequest->load('book.user:id,name,avatar', 'book.author', 'requester:id,name,avatar'), 201);
    }

    /**
     * Accept or reject a book request (book owner only).
     */
    public function update(Request $request, BookRequest $bookRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        // Only the book owner can respond
        if ($bookRequest->book->user_id !== Auth::id()) {
            return response()->json(['message' => 'Solo el dueño del libro puede responder esta solicitud.'], 403);
        }

        $bookRequest->update($validated);

        return response()->json($bookRequest->load('requester:id,name,avatar', 'book.author'));
    }

    /**
     * Cancel a request I sent (while still pending).
     */
    public function destroy(BookRequest $bookRequest)
    {
        if ($bookRequest->requester_id !== Auth::id()) {
            return response()->json(['message' => 'No podés cancelar esta solicitud.'], 403);
        }

        if ($bookRequest->status !== 'pending') {
            return response()->json(['message' => 'Solo podés cancelar solicitudes pendientes.'], 422);
        }

        $bookRequest->delete();

        return response()->json(['message' => 'Solicitud cancelada.']);
    }

    /**
     * Count of pending incoming requests (for notification badge).
     */
    public function pendingCount()
    {
        $myBookIds = Auth::user()->books()->pluck('id');
        $count = BookRequest::whereIn('book_id', $myBookIds)->where('status', 'pending')->count();

        return response()->json(['pending' => $count]);
    }
}
