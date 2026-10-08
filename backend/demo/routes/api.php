<?php

use App\Http\Controllers\Demo\RecordDemoOrderController;
use Illuminate\Support\Facades\Route;

Route::post('b2/demo-orders', RecordDemoOrderController::class)->middleware('throttle:demo-orders')->name('demo.orders.record');
Route::get('b2/health', fn () => response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store'));
