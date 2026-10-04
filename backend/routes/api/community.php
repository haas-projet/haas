<?php

use App\Http\Controllers\HelpRequests\CreateHelpRequestController;
use App\Http\Controllers\HelpRequests\ListHelpRequestsController;
use App\Http\Controllers\HelpRequests\ShowHelpRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/requests', CreateHelpRequestController::class)->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('requests.store');

Route::get('/requests', ListHelpRequestsController::class)->name('requests.index');
Route::get('/requests/{id}', ShowHelpRequestController::class)->whereUuid('id')->name('requests.show');
