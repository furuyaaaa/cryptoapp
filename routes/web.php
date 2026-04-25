<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
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

// 2FA チャレンジ画面は認証済みだが 2FA 未確認のユーザーが触る場所のため、
// auth のみ適用し、`2fa` は意図的に付けない。
Route::middleware('auth')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    // 2FA 検証はユーザー単位＋IP単位の二重バケットで制限する。
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.challenge.store');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', '2fa'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', '2fa', 'throttle:writes'])->group(function () {
    Route::resource('portfolios', PortfolioController::class)
        ->except(['show']);

    // CSVエクスポートは重い処理のため別リミッターで抑制（writes に加えて exports も併用）
    Route::get('transactions/export', [TransactionController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('transactions.export');

    Route::resource('transactions', TransactionController::class)
        ->except(['show']);

    // admin.2fa は管理者ユーザーに 2FA 設定を強制する。未設定なら /profile にリダイレクト。
    Route::middleware(['admin', 'admin.2fa'])->group(function () {
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

Route::middleware(['auth', '2fa', 'throttle:writes'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA の有効化／無効化／復旧コード再発行はプロフィールの一部として扱う。
    Route::post('/profile/two-factor', [TwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.store');
    // confirm も TOTP 検証の一種。challenge と同じ二重バケットで縛る。
    Route::post('/profile/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.confirm');
    Route::delete('/profile/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])
        ->middleware('password.confirm')
        ->name('two-factor.destroy');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');
});

require __DIR__.'/auth.php';
