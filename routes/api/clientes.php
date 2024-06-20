<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Clientes\ClientesController;

Route::prefix('clientes')->group(function () {
    Route::post('/', [ClientesController::class, 'store']);
});

Route::get('/listaclientes', [ClientesController::class, 'index']);
Route::get('/clienteBy/{id}', [ClientesController::class, 'show']);
Route::patch('/clienteUpd/{id}', [ClientesController::class, 'update']); 
Route::delete ('/clienteDel/{id}', [ClientesController::class, 'delete']);