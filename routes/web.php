<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', [GenerationController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/toggle-admin', [ProfileController::class, 'toggleAdmin'])->name('profile.toggle-admin');

    Route::get('/generate', [GenerationController::class, 'create'])->name('generate.create');
    Route::post('/generate/estimate', [GenerationController::class, 'estimate'])->name('generate.estimate');
    Route::post('/generate', [GenerationController::class, 'store'])->name('generate.store');

    Route::get('/history', [GenerationController::class, 'index'])->name('history.index');
    Route::get('/history/poll', [GenerationController::class, 'poll'])->name('history.poll');
    Route::get('/history/{generation}', [GenerationController::class, 'show'])->name('history.show');

    Route::get('/admin/report', [ReportController::class, 'index'])->name('admin.report');

    Route::get('/credits', [CreditController::class, 'create'])->name('credits.create');
    Route::post('/credits', [CreditController::class, 'purchase'])->name('credits.purchase');
});

Route::post('/webhooks/pixelforge', [WebhookController::class, 'handlePixelforge'])->name('webhooks.pixelforge');

require __DIR__.'/auth.php';
