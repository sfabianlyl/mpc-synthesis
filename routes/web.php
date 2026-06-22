<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DrafterController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\SynthesisController;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Http\Request;
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

    // ─── Facilitator Portal ────────────────────────────────────────────
    Route::middleware('role:facilitator,synthesis,drafter')->group(function () {
        Route::get('respond', [ResponseController::class, 'create'])->name('respond.create');
        Route::post('respond', [ResponseController::class, 'store'])->name('respond.store');
    });

    // ─── Synthesis Dashboard ─────────────────────────────────────────
    Route::middleware('role:synthesis')->prefix('synthesis')->name('synthesis.')->group(function () {
        Route::get('/', [SynthesisController::class, 'index'])->name('dashboard');
        Route::get('review/{conversation}', [SynthesisController::class, 'review'])->name('review');
        Route::post('analyze/{conversation}', [SynthesisController::class, 'analyze'])->name('analyze');
        Route::patch('report/{report}', [SynthesisController::class, 'update'])->name('report.update');
        Route::post('report/{report}/submit', [SynthesisController::class, 'submit'])->name('report.submit');
    });

    // ─── Drafter Dashboard & Drafting ──────────────────────────────────
    Route::middleware('role:drafter')->prefix('drafter')->name('drafter.')->group(function () {
        Route::get('/', [DrafterController::class, 'index'])->name('dashboard');
        Route::get('report/{report}', [DrafterController::class, 'show'])->name('report.show');
        Route::post('global-synthesis', [DrafterController::class, 'globalSynthesis'])->name('global-synthesis');

        // Export Page Route
        Route::get('export', function () {
            return inertia('drafter/export');
        })->name('export.page');

        Route::get('export/{document}', [DrafterController::class, 'export'])->name('export');
        Route::get('export/{document}/pdf', [DrafterController::class, 'downloadPdf'])->name('export.pdf');
    });

    // ─── Admin ───────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::post('groups', [AdminController::class, 'storeGroup'])->name('groups.store');
        Route::delete('groups/{group}', [AdminController::class, 'deleteGroup'])->name('groups.destroy');
        Route::patch('users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
        Route::patch('groups/{group}/synthesis', [AdminController::class, 'assignSynthesis'])->name('groups.synthesis');
        Route::patch('groups/{group}/facilitator', [AdminController::class, 'assignFacilitator'])->name('groups.facilitator');
    });
});

require __DIR__.'/settings.php';
