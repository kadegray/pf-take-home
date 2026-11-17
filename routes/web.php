<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/generate', [GenerationController::class, 'create'])->name('generate.create');
    Route::post('/generate/estimate', [GenerationController::class, 'estimate'])->name('generate.estimate');
    Route::post('/generate', [GenerationController::class, 'store'])->name('generate.store');

    Route::get('/history', [GenerationController::class, 'index'])->name('history.index');
    Route::get('/history/poll', [GenerationController::class, 'poll'])->name('history.poll');

    Route::get('/admin/report', [ReportController::class, 'index'])->name('admin.report');
});

Route::post('/webhooks/pixelforge', [WebhookController::class, 'handlePixelforge'])->name('webhooks.pixelforge');

require __DIR__.'/auth.php';
