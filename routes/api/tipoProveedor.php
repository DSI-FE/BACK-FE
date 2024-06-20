<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Proveedores\TipoProveedorController;

Route::prefix('tipos-proveedor')->group(function () {
    Route::post('/', [TipoProveedorController::class, 'store']);
});

Route::get('/listatiposproveedor', [TipoProveedorController::class, 'index']);
Route::get('/tipoproveedorBy/{id}', [TipoProveedorController::class, 'show']);
Route::patch('/tipoproveedorUpd/{id}', [TipoProveedorController::class, 'update']); 
Route::delete('/tipoproveedorDel/{id}', [TipoProveedorController::class, 'delete']);
