<?php

use App\Http\Controllers\API\DTE\ClientesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DTE\CondicionController;
use App\Http\Controllers\API\DTE\DTEController;
use App\Http\Controllers\API\DTE\IdentificacionController;
use App\Http\Controllers\API\DTE\ProveedoresController;
use App\Http\Controllers\API\DTE\TipoDocumentoController;

Route::get('/condicion', [CondicionController::class, 'index']);
Route::get('/identificacion', [IdentificacionController::class, 'index']);
Route::get('/tipo', [TipoDocumentoController::class, 'index']);
Route::get('/clientes', [ClientesController::class, 'index']);
Route::get('/proveedores', [ProveedoresController::class, 'index']);
Route::get('/dte/{id}', [DTEController::class, 'verDte']);
Route::get('/facturadte/{id}', [DTEController::class, 'verVentaDte']);
Route::prefix('adddte/{id}')->group(function () {
    Route::post('/', [DTEController::class, 'index']);
});