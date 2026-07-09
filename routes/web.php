<?php

use App\Http\Controllers\AdminController;
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

    // ─── Synthesis Dashboard ───────────────────────────────��─────────
    Route::middleware('role:synthesis')->prefix('synthesis')->name('synthesis.')->group(function () {
        Route::get('/', [SynthesisController::class, 'index'])->name('dashboard');
        Route::get('review/{conversation}', [SynthesisController::class, 'review'])->name('review');
        Route::post('analyze/{conversation}', [SynthesisController::class, 'analyze'])->name('analyze');
        Route::patch('report/{report}', [SynthesisController::class, 'update'])->name('report.update');
        Route::post('report/{report}/submit', [SynthesisController::class, 'submit'])->name('report.submit');
    });

    // ─── Drafter Dashboard & Drafting ────────────────────────────────
    Route::middleware('role:drafter')->prefix('drafter')->name('drafter.')->group(function () {
        Route::get('/', [DrafterController::class, 'index'])->name('dashboard');
        Route::get('report/{report}', [DrafterController::class, 'show'])->name('report.show');
        Route::post('global-synthesis', [DrafterController::class, 'globalSynthesis'])->name('global-synthesis');

        // Export Page Routes
        Route::get('export', [DrafterController::class, 'export'])->name('export.page');
        Route::get('export/{document}', [DrafterController::class, 'export'])->name('export');
        Route::get('export/{document}/pdf', [DrafterController::class, 'downloadPdf'])->name('export.pdf');
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
