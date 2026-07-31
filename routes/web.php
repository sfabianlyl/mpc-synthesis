<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DocumentRetrievalController;
use App\Http\Controllers\DrafterController;
use App\Http\Controllers\PastoralController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\MasterSynthesisController;
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

    // ─── Discernment Dashboard ────────────────────────────────────────
    Route::middleware('role:discernment')->prefix('discernment')->name('discernment.')->group(function () {
        Route::get('/', function () {
            return inertia('/discernment/dashboard', [
                'currentMode' => request()->query('mode') === 'synthesis' ? 'synthesis' : 'pastoral',
            ]);
        })->name('dashboard');
    });

    // ─── Synthesis Dashboard (read-only for discernment, full access for synthesis) ─────────────────
    Route::middleware('role:synthesis,discernment')->prefix('synthesis')->name('synthesis.')->group(function () {
        Route::get('/', [SynthesisController::class, 'index'])->name('dashboard');
        Route::get('review/{conversation}', [SynthesisController::class, 'review'])->name('review');
        
        // Collaborative Review Page (with real-time updates)
        Route::get('collaborate/{conversation}', [SynthesisController::class, 'reviewPage'])->name('collaborate');
        
        // ─── Synthesis Review Refactoring Routes ─────────────────
        
        // Group assignments
        Route::get('group-assignments/{conversation}', [SynthesisController::class, 'getGroupAssignments'])->name('group-assignments');
        
        // Consensus notes
        Route::post('consensus-note/{conversation}', [SynthesisController::class, 'saveConsensusNote'])->name('consensus-note.save');
        Route::post('consensus-notes-batch/{conversation}', [SynthesisController::class, 'saveBatchConsensusNotes'])->name('consensus-notes.batch');
        Route::get('consensus-notes/{conversation}', [SynthesisController::class, 'getConsensusNotes'])->name('consensus-notes');
        
        // Extraction
        Route::post('extract/{conversation}', [SynthesisController::class, 'triggerExtraction'])->name('extract');
        Route::get('extractions/{conversation}', [SynthesisController::class, 'getExtractions'])->name('extractions');
        
        // Draft management
        Route::patch('draft/{conversation}', [SynthesisController::class, 'saveDraft'])->name('draft.save');
        Route::post('submit-draft/{conversation}', [SynthesisController::class, 'submitDraft'])->name('draft.submit');
        
        // ─── Phase 2: Synthesis Flow Adjustments ─────────────────
        
        // Extraction disable check
        Route::get('can-trigger-extraction/{conversation}', [SynthesisController::class, 'canTriggerExtraction'])->name('can-trigger-extraction');
        
        // Summary jobs
        Route::post('trigger-summary/{conversation}', [SynthesisController::class, 'triggerSummary'])->name('trigger-summary');
        Route::get('summary/{conversation}', [SynthesisController::class, 'getSynthesisSummary'])->name('summary');
        Route::post('check-auto-trigger-summary/{conversation}', [SynthesisController::class, 'checkAndAutoTriggerSummary'])->name('check-auto-trigger-summary');
        
        // Synchronous LLM endpoints (for immediate UI feedback)
        Route::post('extract-sync/{conversation}', [SynthesisController::class, 'extractSync'])->name('extract-sync');
        Route::post('synthesize-draft-sync/{conversation}', [SynthesisController::class, 'synthesizeDraftSync'])->name('synthesize-draft-sync');
        Route::post('summary-sync/{conversation}', [SynthesisController::class, 'summarySync'])->name('summary-sync');
        
        // Master Synthesis editing (all assigned members can edit)
        Route::post('master-synthesis-authorized/{conversation}', [MasterSynthesisController::class, 'updateAuthorized'])->name('master-synthesis-authorized.update');
        
        // Submit Master Synthesis
        Route::post('submit-master-synthesis/{conversation}', [MasterSynthesisController::class, 'submit'])->name('submit-master-synthesis');
        
        // ─── Legacy Routes (kept for backward compatibility) ─────────
        
        // Submit Synthesis (new submission)
        Route::post('submit-synthesis/{conversation}', [SynthesisController::class, 'submitSynthesis'])->name('submit-synthesis');
        
        // Generate AI-assisted synthesis for current user's draft
        Route::post('ai-synthesis/{conversation}', [SynthesisController::class, 'generateAiAssistedSynthesis'])->name('ai-synthesis');
        
        // Master Synthesis routes
        Route::get('conversation/{conversation}/master-synthesis', [MasterSynthesisController::class, 'get'])->name('master-synthesis.get');
        Route::post('conversation/{conversation}/master-synthesis', [MasterSynthesisController::class, 'store'])->name('master-synthesis.store');
        
        // Category-based synthesis retrieval
        Route::get('conversation/{conversation}/syntheses-by-category', [MasterSynthesisController::class, 'getSynthesesByCategory'])->name('syntheses-by-category');
        
        // Update master synthesis (drafter/admin only)
        Route::patch('master-synthesis/{conversation}', [MasterSynthesisController::class, 'update'])->name('master-synthesis.update');
        
        Route::post('analyze/{conversation}', [SynthesisController::class, 'analyze'])->name('analyze');
        Route::patch('report/{report}', [SynthesisController::class, 'update'])->name('report.update');
        Route::post('report/{report}/submit', [SynthesisController::class, 'submit'])->name('report.submit');
        Route::post('custom-query/{conversation}', [SynthesisController::class, 'customQuery'])->name('custom-query');
        Route::delete('custom-query/{query}', [SynthesisController::class, 'deleteCustomQuery'])->name('custom-query.delete');
        
        // Key Points Extraction
        Route::post('extract-key-points/{conversation}', [SynthesisController::class, 'extractKeyPoints'])->name('extract-key-points');
        Route::get('key-points/{conversation}', [SynthesisController::class, 'getKeyPoints'])->name('key-points');
        
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

    // ─── Pastoral Role Routes (accessible by pastoral and discernment) ─────────────────
    Route::middleware(['auth', 'role:pastoral,discernment'])->prefix('pastoral')->name('pastoral.')->group(function () {
        Route::get('/dashboard', [PastoralController::class, 'index'])->name('dashboard');
        Route::get('/reports/{conversation}', [PastoralController::class, 'reports'])->name('reports');
        Route::get('/decision-draft/{conversation}', [PastoralController::class, 'decisionDraft'])->name('decision-draft');
        Route::post('/decision-draft/{conversation}', [PastoralController::class, 'storeDecisionDraft'])->name('decision-draft.store');
        Route::get('/decisions/{conversation}', [PastoralController::class, 'decisions'])->name('decisions');
        Route::post('/decision-summary/{conversation}/{level}', [PastoralController::class, 'decisionSummary'])->name('decision-summary');
        Route::patch('/master-decision/{conversation}/{level}', [PastoralController::class, 'updateMasterDecision'])->name('master-decision.update');

        // Event-level decision making routes
        Route::get('/decision/{event}', [PastoralController::class, 'decision'])->name('decision');
        Route::post('/decision/{event}', [PastoralController::class, 'storeDecisionDrafts'])->name('decision.store');
        Route::get('/collaborate/{event}', [PastoralController::class, 'collaborate'])->name('collaborate');
        Route::post('/collaborate/{event}/generate-summary', [PastoralController::class, 'generateDecisionSummary'])->name('collaborate.generate-summary');
        Route::patch('/collaborate/{event}/save-master-text', [PastoralController::class, 'saveMasterText'])->name('collaborate.save-master-text');
        Route::get('/bishop-overview/{event}', [PastoralController::class, 'bishopOverview'])->name('bishop-overview');
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
