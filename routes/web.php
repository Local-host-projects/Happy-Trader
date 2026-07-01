<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParameterController;

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
    Route::post('/trading/buy',      [TradingController::class, 'manualBuy'])->name('trading.buy');
Route::get('/data/positions',    [TradingController::class, 'positions'])->name('data.positions');

    // JSON data endpoints consumed by JavaScript
    Route::get('/data/stats',     [DashboardController::class, 'stats'])->name('data.stats');
    Route::get('/data/snapshots', [DashboardController::class, 'snapshots'])->name('data.snapshots');
    Route::get('/data/trades',    [TradeController::class, 'data'])->name('data.trades');
});
// Add inside the auth middleware group
Route::get('/data/balance',   [DashboardController::class, 'balance'])->name('data.balance');
Route::get('/data/accounts',  [ProfileController::class, 'accounts'])->name('data.accounts');

Route::get('/settings',                [ProfileController::class, 'index'])->name('settings');
Route::post('/settings/api-key',       [ProfileController::class, 'updateApiKey'])->name('settings.api-key');
Route::post('/settings/switch-account',[ProfileController::class, 'switchAccount'])->name('settings.switch-account');
