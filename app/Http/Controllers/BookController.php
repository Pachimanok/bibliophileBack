<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Auth::user()->books()->with('tags')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (is_string($request->tags)) {
            $request->merge(['tags' => json_decode($request->tags, true)]);
        }
        if (is_string($request->links)) {
            $request->merge(['links' => json_decode($request->links, true)]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'description' => 'nullable|string',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'cover_image' => 'nullable|image|max:5120', // Up to 5MB
            'download_link' => 'nullable|string|max:2048',
            'pages' => 'nullable|integer|min:1',
            'recommended_by' => 'nullable|string|max:255',
            'loaned_to' => 'nullable|string|max:255',
            'format' => 'nullable|string|in:physical,virtual,both,borrowed',
            'reading_status' => 'nullable|string|in:queued,reading,standby,completed,incomplete',
            'summary' => 'nullable|string',
            'reading_notes' => 'nullable|string',
            'lent_by' => 'nullable|string|max:255',
            'links' => 'nullable|array',
            'links.*.title' => 'required_with:links|string|max:255',
            'links.*.url' => 'required_with:links|string|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'public');
            $validated['cover_image'] = $path;
        }

        $author = Auth::user()->authors()->firstOrCreate(['name' => $validated['author']]);
        $validated['author_id'] = $author->id;

        $book = Auth::user()->books()->create($validated);

        if (!empty($validated['tags'])) {
            $tagIds = $this->syncTags($validated['tags']);
            $book->tags()->sync($tagIds);
        }

        return response()->json($book->load('tags', 'author'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        if ($book->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($book->load('tags', 'author'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Book $book)
    {
        if ($book->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (is_string($request->tags)) {
            $request->merge(['tags' => json_decode($request->tags, true)]);
        }
        if (is_string($request->links)) {
            $request->merge(['links' => json_decode($request->links, true)]);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'author' => 'string|max:255',
            'description' => 'nullable|string',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'cover_image' => 'nullable|image|max:5120',
            'download_link' => 'nullable|string|max:2048',
            'pages' => 'nullable|integer|min:1',
            'recommended_by' => 'nullable|string|max:255',
            'loaned_to' => 'nullable|string|max:255',
            'format' => 'nullable|string|in:physical,virtual,both,borrowed',
            'reading_status' => 'nullable|string|in:queued,reading,standby,completed,incomplete',
            'summary' => 'nullable|string',
            'reading_notes' => 'nullable|string',
            'lent_by' => 'nullable|string|max:255',
            'links' => 'nullable|array',
            'links.*.title' => 'required_with:links|string|max:255',
            'links.*.url' => 'required_with:links|string|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            // Delete old cover if exists
            if ($book->cover_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($book->cover_image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($book->cover_image);
            }
            $path = $request->file('cover_image')->store('covers', 'public');
            $validated['cover_image'] = $path;
        }

        if (isset($validated['author'])) {
            $author = Auth::user()->authors()->firstOrCreate(['name' => $validated['author']]);
            $validated['author_id'] = $author->id;
        }

        $book->update($validated);

        if (isset($validated['tags'])) {
            $tagIds = $this->syncTags($validated['tags']);
            $book->tags()->sync($tagIds);
        }

        return response()->json($book->load('tags', 'author'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book)
    {
        if ($book->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $book->delete();

        return response()->json(['message' => 'Book deleted successfully']);
    }

    /**
     * Helper to find or create tags for the user.
     */
    private function syncTags(array $tagNames): array
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Auth::user()->tags()->firstOrCreate(['name' => $name]);
            $tagIds[] = $tag->id;
        }
        return $tagIds;
    }
}
