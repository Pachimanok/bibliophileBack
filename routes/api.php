<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\BookRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/books/reorder-queue', [BookController::class, 'reorderQueue']);
    Route::apiResource('books', BookController::class);
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('authors', AuthorController::class);

    // ── Communities ────────────────────────────────────────────────────────
    Route::get('/communities', [CommunityController::class, 'index']);
    Route::get('/communities/public', [CommunityController::class, 'publicIndex']);
    Route::post('/communities', [CommunityController::class, 'store']);
    Route::get('/communities/{community}', [CommunityController::class, 'show']);
    Route::put('/communities/{community}', [CommunityController::class, 'update']);
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy']);
    Route::post('/communities/join', [CommunityController::class, 'join']);
    Route::delete('/communities/{community}/leave', [CommunityController::class, 'leave']);
    Route::post('/communities/{community}/approve', [CommunityController::class, 'approveMember']);
    Route::post('/communities/{community}/reject', [CommunityController::class, 'rejectMember']);
    Route::post('/communities/{community}/regenerate-code', [CommunityController::class, 'regenerateCode']);
    Route::get('/communities/{community}/library', [CommunityController::class, 'library']);

    // ── Book Requests ──────────────────────────────────────────────────────
    Route::get('/book-requests', [BookRequestController::class, 'index']);
    Route::get('/book-requests/incoming', [BookRequestController::class, 'incoming']);
    Route::get('/book-requests/pending-count', [BookRequestController::class, 'pendingCount']);
    Route::post('/book-requests', [BookRequestController::class, 'store']);
    Route::put('/book-requests/{bookRequest}', [BookRequestController::class, 'update']);
    Route::delete('/book-requests/{bookRequest}', [BookRequestController::class, 'destroy']);
});

require __DIR__.'/auth.php';


// Fallback for serving storage files via API
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        return response()->json([
            'error' => 'File not found',
            'full_path' => $fullPath
        ], 404);
    }
    return response()->file($fullPath);
})->where('path', '.*');
