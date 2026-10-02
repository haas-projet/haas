<?php

use App\Http\Controllers\Identity\ForgotPasswordController;
use App\Http\Controllers\Identity\LoginController;
use App\Http\Controllers\Identity\LogoutController;
use App\Http\Controllers\Identity\RegisterMemberController;
use App\Http\Controllers\Identity\ResendVerificationController;
use App\Http\Controllers\Identity\ResetPasswordController;
use App\Http\Controllers\Identity\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterMemberController::class)->middleware('throttle:5,1')->name('register');
Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth:web')->name('logout');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:password-link')->name('password.email');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:password-reset')->name('password.update');
Route::post('/email/verification-notification', ResendVerificationController::class)->middleware(['auth:web', 'throttle:verification-send'])->name('verification.send');
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['auth:web', 'signed', 'throttle:6,1'])->name('verification.verify');
