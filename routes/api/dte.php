<?php

use App\Http\Controllers\API\DTE\ClientesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DTE\CondicionController;
use App\Http\Controllers\API\DTE\ContingenciaController;
use App\Http\Controllers\API\DTE\DTEController;
use App\Http\Controllers\API\DTE\IdentificacionController;
use App\Http\Controllers\API\DTE\ProveedoresController;
use App\Http\Controllers\API\DTE\ResponsablesController;
use App\Http\Controllers\API\DTE\TipoContingenciaController;
use App\Http\Controllers\API\DTE\TipoDocumentoController;
use App\Http\Controllers\API\DTE\TipoInvalidacionController;

Route::get('/condicion', [CondicionController::class, 'index']);
Route::get('/identificacion', [IdentificacionController::class, 'index']);
Route::get('/tipo', [TipoDocumentoController::class, 'index']);
Route::get('/clientes', [ClientesController::class, 'index']);
Route::get('/proveedores', [ProveedoresController::class, 'index']);
Route::get('/dte/{id}', [DTEController::class, 'verDte']);
Route::get('/facturadte/{id}', [DTEController::class, 'verVentaDte']);
Route::prefix('adddte/{id}/{contingenciaId}')->group(function () {
    Route::post('/', [DTEController::class, 'index']);
});
Route::get('/invalidacion', [TipoInvalidacionController::class, 'index']);
Route::patch('/invalidacion/{idVenta}', [DTEController::class, 'agregarInvalidacion']);
Route::get('/responsables', [ResponsablesController::class, 'index']);
Route::get('/contingencias', [ContingenciaController::class, 'index']);
Route::get('/tipocontingencias', [TipoContingenciaController::class, 'index']);
Route::get('/contingencias/{id}', [ContingenciaController::class, 'DTEContingencia']);
Route::patch('/fincontingencia/{id}', [ContingenciaController::class, 'FinalizarContingencia']);
Route::get('transmitir/{id}', [DTEController::class, 'TransmitirContingencia']);
Route::prefix('iniciarcontingencia')->group(function () {
    Route::post('/', [ContingenciaController::class, 'IniciarContingencia']);
});
Route::get('/contingenciaactiva', [ContingenciaController::class, 'verificarContingencia']);