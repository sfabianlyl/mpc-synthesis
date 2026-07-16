<?php

use App\Http\Controllers\DocumentRetrievalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('document-sources')->group(function () {
    // Retrieve chunks for booklet generation
    Route::get('/retrieve', [DocumentRetrievalController::class, 'retrieve'])
        ->name('api.documents.retrieve');

    // Get all chunks from a specific source
    Route::get('/{sourceId}/chunks', [DocumentRetrievalController::class, 'getChunks'])
        ->name('api.document-chunks.index');

    // Search chunks by keyword
    Route::get('/search', [DocumentRetrievalController::class, 'search'])
        ->name('api.documents.search');

    // Get all available document sources
    Route::get('/sources', [DocumentRetrievalController::class, 'getSources'])
        ->name('api.documents.sources');

    // Get document statistics
    Route::get('/statistics', [DocumentRetrievalController::class, 'getStatistics'])
        ->name('api.documents.statistics');
});
