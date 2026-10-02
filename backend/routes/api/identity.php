<?php

use App\Http\Controllers\Identity\AccountAccessController;
use App\Http\Controllers\Identity\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->middleware('auth:sanctum')->name('me');
Route::get('/account-access', AccountAccessController::class)->name('account.access');
