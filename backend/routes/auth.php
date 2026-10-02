<?php

use App\Http\Controllers\Identity\LoginController;
use App\Http\Controllers\Identity\LogoutController;
use App\Http\Controllers\Identity\RegisterMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterMemberController::class)->middleware('throttle:5,1')->name('register');
Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth:web')->name('logout');
