<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Compras\ComprasController;

Route::prefix('addcompra')->group(function () {
    Route::post('/', [ComprasController::class, 'store']);
});
Route::get('/compras', [ComprasController::class, 'index']);
Route::get('/detallecompra/{numero}', [ComprasController::class, 'detalleCompra']);
Route::patch('/comprasUpd/{id}', [ComprasController::class, 'update']);
Route::delete('/delete/{id}', [ComprasController::class, 'delete']);