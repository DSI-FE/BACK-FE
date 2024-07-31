<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Inventario\InventarioController;

Route::prefix('addinventario')->group(function () {
    Route::post('/', [InventarioController::class, 'store']);
});
Route::get('/inventario', [InventarioController::class, 'index']);
Route::get('/productoByCod/{codigo}', [InventarioController::class, 'show']);
Route::patch('/inventarioUpd/{id}/{unidad_medida_id}', [InventarioController::class, 'update']); 
Route::get('/sumaCosto', [InventarioController::class, 'sumaInventario']);
//sin usar
Route::delete ('/inventariodel/{id}/{unidad}', [InventarioController::class, 'delete']);

