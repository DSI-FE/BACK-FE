<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Productos\ProductosController;


Route::get('/productos', [ProductosController::class, 'index']);
