<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SocialAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Fallback for serving storage files if the storage:link symlink is missing
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        $dir = dirname($fullPath);
        $files = is_dir($dir) ? scandir($dir) : 'Directory does not exist';
        return response()->json([
            'error' => 'File not found',
            'requested_path' => $path,
            'full_path' => $fullPath,
            'dir_exists' => is_dir($dir),
            'files_in_dir' => $files
        ], 404);
    }
    return response()->file($fullPath);
})->where('path', '.*');

// Google OAuth Routes
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
