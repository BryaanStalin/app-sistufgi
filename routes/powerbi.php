<?php

use App\Http\Controllers\PowerBiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PowerBiController::class, 'health'])->name('health');
Route::get('campanias/{campania}/resumen', [PowerBiController::class, 'resumen'])
    ->whereNumber('campania')
    ->name('campanias.resumen');
Route::get('campanias/{campania}/servicios', [PowerBiController::class, 'servicios'])
    ->whereNumber('campania')
    ->name('campanias.servicios');
