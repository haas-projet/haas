<?php

use App\Http\Controllers\Identity\RegisterMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterMemberController::class)->middleware('throttle:5,1')->name('register');
