<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminMediaUploadController;
use App\Http\Controllers\DocumentRetrievalController;
use App\Http\Controllers\DrafterController;
use App\Http\Controllers\PastoralController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\MasterSynthesisController;
use App\Http\Controllers\SynthesisController;
use App\Models\MasterSynthesis;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Response;
use App\Models\Diocese;
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
        Route::get('collaborate/{conversation}', [SynthesisController::class, 'collaboratePage'])->name('collaborate');
        
        // ─── Synthesis Review Refactoring Routes ─────────────────
        
        // Group assignments
        Route::get('group-assignments/{conversation}', [SynthesisController::class, 'getGroupAssignments'])->name('group-assignments');
        
        // Consensus notes batch
        Route::post('consensus-notes-batch/{conversation}', [SynthesisController::class, 'saveBatchConsensusNotes'])->name('consensus-notes.batch');
        
        // Extraction
        Route::post('extract/{conversation}', [SynthesisController::class, 'triggerExtraction'])->name('extract');
        
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
        
        // Master Synthesis routes
        Route::get('conversation/{conversation}/master-synthesis', [MasterSynthesisController::class, 'get'])->name('master-synthesis.get');
        Route::post('conversation/{conversation}/master-synthesis', [MasterSynthesisController::class, 'store'])->name('master-synthesis.store');
        
        // // Category-based synthesis retrieval
        // Route::get('conversation/{conversation}/syntheses-by-category', [MasterSynthesisController::class, 'getSynthesesByCategory'])->name('syntheses-by-category');
        
        // Update master synthesis (drafter/admin only)
        Route::patch('master-synthesis/{conversation}', [MasterSynthesisController::class, 'update'])->name('master-synthesis.update');
        
        Route::post('custom-query/{conversation}', [SynthesisController::class, 'customQuery'])->name('custom-query');
        Route::delete('custom-query/{query}', [SynthesisController::class, 'deleteCustomQuery'])->name('custom-query.delete');
        
        // Key Points Extraction
        Route::post('extract-key-points/{conversation}', [SynthesisController::class, 'extractKeyPoints'])->name('extract-key-points');
        Route::get('key-points/{conversation}', [SynthesisController::class, 'getKeyPoints'])->name('key-points');
        Route::patch('key-points/{conversation}', [SynthesisController::class, 'updateKeyPoints'])->name('key-points.update');
        
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

        // Step 1: Per-Conversation Decision Draft (Member Scope)
        Route::get('/decision/{conversation}', [PastoralController::class, 'conversationDecision'])->name('decision');
        Route::post('/decision/{conversation}', [PastoralController::class, 'storeConversationDraft'])->name('decision.store');

        // Step 2: Per-Conversation Collaborative DecisionPart (Shared Scope)
        Route::get('/collaborate/{conversation}', [PastoralController::class, 'collaborateConversation'])->name('collaborate');
        Route::post('/collaborate/{conversation}/generate-summary', [PastoralController::class, 'generateDecisionPartSummary'])->name('collaborate.generate-summary');
        Route::patch('/collaborate/{conversation}/save-part', [PastoralController::class, 'saveDecisionPart'])->name('collaborate.save-part');
        Route::post('/collaborate/{conversation}/finalize-part', [PastoralController::class, 'finalizeDecisionPart'])->name('collaborate.finalize-part');

        // Step 3: Event-Level Master Decision Review & Finalization
        Route::get('/master-decision/{event}', [PastoralController::class, 'masterDecision'])->name('master-decision');
        Route::post('/master-decision/{event}/generate-summary', [PastoralController::class, 'generateMasterDecisionSummary'])->name('master-decision.generate-summary');
        Route::patch('/master-decision/{event}/save', [PastoralController::class, 'saveMasterDecision'])->name('master-decision.save');
        Route::post('/master-decision/{event}/finalize', [PastoralController::class, 'finalizeMasterDecision'])->name('master-decision.finalize');

        // Step 4: Bishop's Overview
        Route::get('/bishop-overview/{event}', [PastoralController::class, 'bishopOverview'])->name('bishop-overview');

        // Booklet export (accessible by pastoral and discernment)
        Route::get('/export-booklet/{conversation}', [\App\Http\Controllers\SynthesisController::class, 'exportBooklet'])->name('export-booklet');
    });

    // ─── Admin Chat Routes ────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/chat')->name('admin.chat.')->group(function () {
        Route::get('/', [AdminController::class, 'chat'])->name('index');
        Route::get('/details', [AdminController::class, 'chatDetails'])->name('details');
        Route::get('/update-db', function(){

            // $dioceses = Diocese::all();
            // // First names for generating user accounts
            // $pastoralFirstNames = [
            //     'KL', // kl
            //     'Penang', //penang
            //     'MJD', //mjd
            //     'Kuching', //kuching
            //     'Sibu', //sibu
            //     'Miri', //miri
            //     'KK', // kk
            //     'Sandakan', //sandakan
            //     'Keningau' //keningau
            // ];

            
            // // Create 1 pastoral account per diocese
            // foreach ($dioceses as $i=>$diocese) {
            //     $firstName = $pastoralFirstNames[$i] ?? 'Pastoral';
            //     $email1 = strtolower($firstName."01@gerejakatolik.my");
            //     $email2 = strtolower($firstName."02@gerejakatolik.my");

            //     User::firstOrCreate(
            //         ['email' => $email1],
            //         [
            //             'name' => $firstName."01",
            //             'role' => 'pastoral',
            //             'diocese_id' => $diocese->id,
            //             'password' => bcrypt('password'),
            //             'email_verified_at' => now(),
            //         ]
            //     );

            //     User::firstOrCreate(
            //         ['email' => $email2],
            //         [
            //             'name' => $firstName."02",
            //             'role' => 'pastoral',
            //             'diocese_id' => $diocese->id,
            //             'password' => bcrypt('password'),
            //             'email_verified_at' => now(),
            //         ]
            //     );
            // }

            // //bishops
            // User::firstOrCreate(
            //         ['email' => "bernard@gerejakatolik.my"],
            //         [
            //             'name' => "Bishop Bernard Paul",
            //             'role' => 'pastoral',
            //             'diocese_id' => 3,
            //             'password' => bcrypt('password'),
            //             'email_verified_at' => now(),
            //         ]
            //     );
            // User::firstOrCreate(
            //         ['email' => "julius@gerejakatolik.my"],
            //         [
            //             'name' => "Bishop Julius Gitom",
            //             'role' => 'pastoral',
            //             'diocese_id' => 8,
            //             'password' => bcrypt('password'),
            //             'email_verified_at' => now(),
            //         ]
            //     );
            // User::firstOrCreate(
            //         ['email' => "richard@gerejakatolik.my"],
            //         [
            //             'name' => "Bishop Richard Ng",
            //             'role' => 'pastoral',
            //             'diocese_id' => 6,
            //             'password' => bcrypt('password'),
            //             'email_verified_at' => now(),
            //         ]
            //     );
            
            return response()->json([
                "success"=>true
            ], 200);
        })->name('update.db');
        
        Route::post('/message', [AdminController::class, 'chatMessage'])->name('message');
    });

    // ─── Admin Media Upload Routes ──────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/media-upload')->name('admin.media-upload.')->group(function () {
        Route::get('/', [AdminMediaUploadController::class, 'index'])->name('');
        Route::post('/{conversation}', [AdminMediaUploadController::class, 'store'])->name('store');
    });
});

// ─── Public Anonymous Response Routes (No Auth Required) ─────────────────
Route::get('respond/{event_id}/{group_id}', [ResponseController::class, 'showPublicForm'])
    ->name('respond.public.form');
Route::post('respond/{event_id}/{group_id}', [ResponseController::class, 'storePublicResponse'])
    ->name('respond.public.store');

require __DIR__.'/settings.php';
