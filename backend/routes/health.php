<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;

// Sonde de démarrage uniquement : aucune session, donnée privée ou requête SQL.
Route::get('/up', static fn () => new JsonResponse(['status' => 'ok']))
    ->name('health');
