<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TransactionController;
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

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'throttle:writes'])->group(function () {
    Route::resource('portfolios', PortfolioController::class)
        ->except(['show']);

    // CSVエクスポートは重い処理のため別リミッターで抑制（writes に加えて exports も併用）
    Route::get('transactions/export', [TransactionController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('transactions.export');

    Route::resource('transactions', TransactionController::class)
        ->except(['show']);

    Route::middleware(['admin'])->group(function () {
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    Route::get('/assets/{symbol}', [AssetController::class, 'show'])
        ->name('assets.show');

    Route::get('/exchanges', [ExchangeController::class, 'index'])
        ->name('exchanges.index');

    Route::get('/tax/export', [TaxController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('tax.export');
    Route::get('/tax', [TaxController::class, 'index'])->name('tax.index');
});

Route::middleware(['auth', 'throttle:writes'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
