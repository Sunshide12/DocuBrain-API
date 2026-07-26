<?php

use App\Http\Controllers\Api\DocumentDownloadController;
use App\Http\Controllers\Api\DocumentUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/documents/upload', DocumentUploadController::class);
    Route::get('/documents/{document}/download', DocumentDownloadController::class);
});
