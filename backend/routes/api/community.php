<?php

use App\Http\Controllers\HelpRequests\CreateHelpRequestController;
use App\Http\Controllers\HelpRequests\ListHelpRequestRevisionsController;
use App\Http\Controllers\HelpRequests\ListHelpRequestsController;
use App\Http\Controllers\HelpRequests\PublishHelpRequestController;
use App\Http\Controllers\HelpRequests\ShowHelpRequestController;
use App\Http\Controllers\HelpRequests\UpdateHelpRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/requests', CreateHelpRequestController::class)->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('requests.store');

Route::get('/requests', ListHelpRequestsController::class)->name('requests.index');
Route::get('/requests/{id}', ShowHelpRequestController::class)->whereUuid('id')->name('requests.show');

Route::patch('/requests/{id}', UpdateHelpRequestController::class)->whereUuid('id')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('requests.update');
Route::post('/requests/{id}/publish', PublishHelpRequestController::class)->whereUuid('id')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('requests.publish');
Route::get('/requests/{id}/revisions', ListHelpRequestRevisionsController::class)->whereUuid('id')->name('requests.revisions');
