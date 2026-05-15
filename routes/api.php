<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\AuthorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('books', BookController::class);
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('authors', AuthorController::class);
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
