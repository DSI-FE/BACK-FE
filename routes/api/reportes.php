<?php

use App\Http\Controllers\API\Reportes\ReportesController;
use Illuminate\Support\Facades\Route;

//PDF
Route::get('/consumidor/{fecha}', [ReportesController::class, 'consumidor']);
Route::get('/contribuyente/{fecha}', [ReportesController::class, 'contribuyente']);
Route::get('/inventario', [ReportesController::class, 'inventario']);
Route::get('/compras/{fecha}', [ReportesController::class, 'compras']);
Route::get('ticket/{id}', [ReportesController::class, 'ticket']);
//Excel
Route::get('/consumidor-excel/{fecha}', [ReportesController::class, 'consumidorExcel']);
Route::get('/contribuyente-excel/{fecha}', [ReportesController::class, 'contribuyenteExcel']);
Route::get('/inventario-excel', [ReportesController::class, 'inventarioExcel']);
Route::get('/compras-excel/{fecha}', [ReportesController::class, 'comprasExcel']);
