<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Inventario\InventarioController;

Route::prefix('addinventario')->group(function () {
    Route::post('/', [InventarioController::class, 'store']);
});
Route::get('/inventario', [InventarioController::class, 'index']);
Route::get('/productoByCod/{codigo}', [InventarioController::class, 'show']);
Route::patch('/inventarioUpd/{id}', [InventarioController::class, 'update']); 
Route::get('/productoBy/{id}', [InventarioController::class, 'codigo']); 
Route::get('/sumaCosto', [InventarioController::class, 'sumaInventario']);
//sin usar
Route::delete ('/inventariodel/{id}', [InventarioController::class, 'delete']);

