<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Ventas\VentasController;

Route::prefix('addventa')->group(function () {
    Route::post('/', [VentasController::class, 'store']);
});
Route::get('/ventas', [VentasController::class, 'index']);
Route::get('/detalleventa/{id}', [VentasController::class, 'detalleVenta']);
Route::patch('/ventaUpd/{id}', [VentasController::class, 'update']);
Route::delete('/delete/{id}', [VentasController::class, 'delete']);
Route::get('/{id}/factura', [VentasController::class, 'descargarFactura']);