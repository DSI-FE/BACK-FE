<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Productos\ProductosController;


Route::get('/productos', [ProductosController::class, 'index']);
Route::get('/unidades', [ProductosController::class, 'show']);
Route::get('tiposproductos', [ProductosController::class, 'tiposproductos']);
Route::get('/anular/{id}', [ProductosController::class, 'AnularFactura']);
Route::get('/contingencia/{id}', [ProductosController::class, 'Contingencia']);
Route::get('/json/{id}', [ProductosController::class, 'prueba']);
Route::get('/second', [ProductosController::class, 'second']);
Route::get('/user', [ProductosController::class, 'user']);
