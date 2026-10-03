<?php

use App\Http\Controllers\Identity\AccountAccessController;
use App\Http\Controllers\Identity\AdministrationController;
use App\Http\Controllers\Identity\MeController;
use App\Http\Controllers\Identity\OwnProfileController;
use App\Http\Controllers\Identity\PublicProfileController;
use App\Http\Controllers\Identity\TechnologyController;
use App\Http\Controllers\Identity\UpdateProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->middleware('auth:sanctum')->name('me');
Route::get('/account-access', AccountAccessController::class)->name('account.access');
Route::get('/members/{handle}', PublicProfileController::class)->name('profiles.show');
Route::get('/technologies', TechnologyController::class)->name('technologies.index');
Route::get('/me/profile', OwnProfileController::class)->middleware('auth:sanctum')->name('profiles.own');
Route::patch('/me/profile', UpdateProfileController::class)->middleware(['auth:sanctum', 'verified'])->name('profiles.update');
Route::get('/admin/members', [AdministrationController::class, 'index'])->middleware(['auth:sanctum', 'verified'])->name('administration.members');
Route::patch('/admin/members/{id}', [AdministrationController::class, 'update'])->whereUuid('id')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('administration.update');
