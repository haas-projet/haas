<?php

use App\Http\Controllers\Identity\AccountAccessController;
use App\Http\Controllers\Identity\AdministrationController;
use App\Http\Controllers\Identity\MeController;
use App\Http\Controllers\Identity\OwnProfileController;
use App\Http\Controllers\Identity\PublicProfileController;
use App\Http\Controllers\Identity\TechnologyController;
use App\Http\Controllers\Identity\UpdateProfileController;
use App\Http\Controllers\Moderation\ReportController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->middleware('auth:sanctum')->name('me');
Route::post('/reports', [ReportController::class, 'store'])->middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->name('reports.store');
Route::get('/admin/reports', [ReportController::class, 'index'])->middleware(['auth:sanctum', 'verified'])->name('reports.index');
Route::get('/admin/reports/{id}', [ReportController::class, 'show'])->whereUuid('id')->middleware(['auth:sanctum', 'verified'])->name('reports.show');
Route::post('/admin/reports/{id}/decisions', [ReportController::class, 'decide'])->whereUuid('id')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('reports.decide');
Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth:sanctum')->name('notifications.index');
Route::patch('/notifications/{id}', [NotificationController::class, 'update'])->whereUuid('id')->middleware('auth:sanctum')->name('notifications.update');
Route::get('/account-access', AccountAccessController::class)->name('account.access');
Route::get('/members/{handle}', PublicProfileController::class)->name('profiles.show');
Route::get('/technologies', TechnologyController::class)->name('technologies.index');
Route::get('/me/profile', OwnProfileController::class)->middleware('auth:sanctum')->name('profiles.own');
Route::patch('/me/profile', UpdateProfileController::class)->middleware(['auth:sanctum', 'verified'])->name('profiles.update');
Route::get('/admin/members', [AdministrationController::class, 'index'])->middleware(['auth:sanctum', 'verified'])->name('administration.members');
Route::patch('/admin/members/{id}', [AdministrationController::class, 'update'])->whereUuid('id')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('administration.update');
