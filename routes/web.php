<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DocumentRetrievalController;
use App\Http\Controllers\DrafterController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\SynthesisController;
use Illuminate\Support\Facades\Route;

Route::inertia('/login', 'auth/login')->name('login');
Route::post('logout', function () {
    auth()->logout();
    return to_route('login');
})->name('logout');

// ─── Authenticated User Routes ─────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // Index URL - Main hub for all authenticated users
    Route::inertia('/', 'home')->name('home');
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // ─── Synthesis Dashboard ────────────────────────────────────────
    Route::middleware('role:synthesis')->prefix('synthesis')->name('synthesis.')->group(function () {
        Route::get('/', [SynthesisController::class, 'index'])->name('dashboard');
        Route::get('review/{conversation}', [SynthesisController::class, 'review'])->name('review');
        
        // Collaborative Review Page (with real-time updates)
        Route::get('review-page/{conversation}', [SynthesisController::class, 'reviewPage'])->name('review-page');
        
        // Submit Synthesis (new submission)
        Route::post('submit-synthesis/{conversation}', [SynthesisController::class, 'submitSynthesis'])->name('submit-synthesis');
        
        // Generate AI-assisted synthesis for current user's draft
        Route::post('ai-synthesis/{conversation}', [SynthesisController::class, 'generateAiAssistedSynthesis'])->name('ai-synthesis');
        
        // Master Synthesis routes
        Route::get('conversation/{conversation}/master-synthesis', [SynthesisController::class, 'getMasterSynthesis'])->name('master-synthesis.get');
        Route::post('conversation/{conversation}/master-synthesis', [SynthesisController::class, 'storeMasterSynthesis'])->name('master-synthesis.store');
        
        // Category-based synthesis retrieval
        Route::get('conversation/{conversation}/syntheses-by-category', [SynthesisController::class, 'getSynthesesByCategory'])->name('syntheses-by-category');
        
        // Update master synthesis (drafter/admin only)
        Route::patch('master-synthesis/{conversation}', [SynthesisController::class, 'updateMasterSynthesis'])->name('master-synthesis.update');
        
        Route::post('analyze/{conversation}', [SynthesisController::class, 'analyze'])->name('analyze');
        Route::patch('report/{report}', [SynthesisController::class, 'update'])->name('report.update');
        Route::post('report/{report}/submit', [SynthesisController::class, 'submit'])->name('report.submit');
        Route::post('custom-query/{conversation}', [SynthesisController::class, 'customQuery'])->name('custom-query');
        Route::delete('custom-query/{query}', [SynthesisController::class, 'deleteCustomQuery'])->name('custom-query.delete');
        
        // Export Booklet
        Route::get('export-booklet/{conversation}', [SynthesisController::class, 'exportBooklet'])->name('export-booklet');
    });

    // ─── Drafter Dashboard & Drafting ────────────────────────────────
    Route::middleware('role:drafter')->prefix('drafter')->name('drafter.')->group(function () {
        Route::get('/', [DrafterController::class, 'index'])->name('dashboard');
        Route::get('report/{report}', [DrafterController::class, 'show'])->name('report.show');
        Route::post('global-synthesis', [DrafterController::class, 'globalSynthesis'])->name('global-synthesis');

        // Booklet generation routes
        Route::post('booklet/event/{event}/generate-with-sources', [DrafterController::class, 'generateBookletWithSources'])->name('booklet.generate-with-sources');
        Route::get('booklet/event/{event}/reports', [DrafterController::class, 'getEventReports'])->name('booklet.event-reports');

        // Export Page Routes
        Route::get('export', [DrafterController::class, 'export'])->name('export.page');
        Route::get('export/{document}', [DrafterController::class, 'export'])->name('export');
        Route::get('export/{document}/pdf', [DrafterController::class, 'downloadPdf'])->name('export.pdf');
    });

    // ─── Document Retrieval Routes ─────────────────────────────────────
    Route::middleware('role:drafter')->prefix('documents')->name('documents.')->group(function () {
        // Search for relevant document chunks with semantic similarity
        Route::get('search-chunks', [DocumentRetrievalController::class, 'searchChunks'])->name('search-chunks');
        
        // Upload PDF documents
        Route::post('upload', [DocumentRetrievalController::class, 'uploadDocument'])->name('upload');
        
        // Delete uploaded document
        Route::delete('{sourceId}', [DocumentRetrievalController::class, 'deleteDocument'])->name('delete');
        
        // Get conversation documents
        Route::get('conversation/{conversationId}', [DocumentRetrievalController::class, 'getConversationDocuments'])->name('conversation-documents');
        
        // Get available sources and statistics
        Route::get('sources', [DocumentRetrievalController::class, 'getSources'])->name('sources');
        Route::get('statistics', [DocumentRetrievalController::class, 'getStatistics'])->name('statistics');
    });

    // ─── Admin Chat Routes ────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/chat')->name('admin.chat.')->group(function () {
        Route::get('/', [AdminController::class, 'chat'])->name('/');
        Route::get('/details', [AdminController::class, 'chatDetails'])->name('.details');
        Route::post('/message', [AdminController::class, 'chatMessage'])->name('.message');
    });
});

// ─── Public Anonymous Response Routes (No Auth Required) ─────────────────
Route::get('respond/{event_id}/{group_id}', [ResponseController::class, 'showPublicForm'])
    ->name('respond.public.form');
Route::post('respond/{event_id}/{group_id}', [ResponseController::class, 'storePublicResponse'])
    ->name('respond.public.store');

require __DIR__.'/settings.php';
