<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthorController extends Controller
{
    public function index()
    {
        return response()->json(Auth::user()->authors()->withCount('books')->get());
    }

    public function show(Author $author)
    {
        if ($author->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json($author->load('books', 'books.tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'biography' => 'nullable|string',
            'birth_date' => 'nullable|date',
        ]);

        $author = Auth::user()->authors()->create($validated);
        return response()->json($author, 201);
    }

    public function update(Request $request, Author $author)
    {
        if ($author->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'biography' => 'nullable|string',
            'birth_date' => 'nullable|date',
        ]);

        $author->update($validated);
        return response()->json($author);
    }

    public function destroy(Author $author)
    {
        if ($author->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $author->delete();
        return response()->noContent();
    }
}
