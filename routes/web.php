<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;

Route::get('/', function () {
    return redirect()->route('portfolios.index');
});

Route::resource('portfolios', PortfolioController::class);