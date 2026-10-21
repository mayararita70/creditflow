<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/account', [AccountController::class, 'show']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions/credit', [TransactionController::class, 'credit']);
    Route::post('/transactions/debit', [TransactionController::class, 'debit']);
    Route::post('/transactions/{id}/reverse', [TransactionController::class, 'reverse']);
});