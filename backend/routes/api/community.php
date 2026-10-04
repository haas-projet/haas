<?php

use App\Http\Controllers\HelpRequests\CreateHelpRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/requests', CreateHelpRequestController::class)->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->name('requests.store');
