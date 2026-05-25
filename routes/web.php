<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\TradeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/',           [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/trades',     [TradeController::class, 'index'])->name('trades');
    Route::get('/parameters', [ParameterController::class, 'index'])->name('parameters');

    Route::put('/parameters',      [ParameterController::class, 'update'])->name('parameters.update');
    Route::post('/trading/toggle', [ParameterController::class, 'toggleTrading'])->name('trading.toggle');

    // JSON data endpoints consumed by JavaScript
    Route::get('/data/stats',     [DashboardController::class, 'stats'])->name('data.stats');
    Route::get('/data/snapshots', [DashboardController::class, 'snapshots'])->name('data.snapshots');
    Route::get('/data/trades',    [TradeController::class, 'data'])->name('data.trades');
});