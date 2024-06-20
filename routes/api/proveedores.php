<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Proveedores\ProveedoresController;

Route::prefix('proveedores')->group(function () {
    Route::post('/', [ProveedoresController::class, 'store']);
});

Route::get('/listaproveedores', [ProveedoresController::class, 'index']);
Route::get('/proveedorBy/{id}', [ProveedoresController::class, 'show']);
Route::patch('/proveedorUpd/{id}', [ProveedoresController::class, 'update']); 
Route::delete ('/proveedorDel/{id}', [ProveedoresController::class, 'delete']);