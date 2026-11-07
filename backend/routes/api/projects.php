<?php

use App\Http\Controllers\Projects\CreateProjectController;
use App\Http\Controllers\Projects\UpdateProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->group(function (): void {
    Route::post('projects', CreateProjectController::class)->name('projects.store');
    Route::patch('projects/{id}', UpdateProjectController::class)->whereUuid('id')->name('projects.update');
});
