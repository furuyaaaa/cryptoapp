<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PortfolioController as ApiPortfolioController;
use App\Http\Controllers\Api\V1\TransactionController as ApiTransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| モバイル / 外部クライアント向け JSON API（Bearer Sanctum）
|--------------------------------------------------------------------------
| ベース URL: /api/v1
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);

        Route::get('dashboard', DashboardController::class);

        Route::get('portfolios', [ApiPortfolioController::class, 'index']);
        Route::post('portfolios', [ApiPortfolioController::class, 'store']);
        Route::patch('portfolios/{portfolio}', [ApiPortfolioController::class, 'update']);
        Route::delete('portfolios/{portfolio}', [ApiPortfolioController::class, 'destroy']);

        Route::get('transactions/form', [ApiTransactionController::class, 'form']);
        Route::get('transactions', [ApiTransactionController::class, 'index']);
        Route::post('transactions', [ApiTransactionController::class, 'store']);
        Route::patch('transactions/{transaction}', [ApiTransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [ApiTransactionController::class, 'destroy']);
    });
});
