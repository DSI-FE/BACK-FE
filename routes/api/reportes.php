<?php

use App\Http\Controllers\API\Reportes\ReportesController;
use Illuminate\Support\Facades\Route;

//PDF
Route::get('/consumidor/{fechaInicio}/{fechaFin}', [ReportesController::class, 'consumidor']);
Route::get('/contribuyente/{fechaInicio}/{fechaFin}', [ReportesController::class, 'contribuyente']);
Route::get('/inventario', [ReportesController::class, 'inventario']);
Route::get('/compras/{mes}/{anio}', [ReportesController::class, 'compras']);
Route::get('ticket/{id}', [ReportesController::class, 'ticket']);
//Excel
Route::get('/consumidor-excel/{fechaInicio}/{fechaFin}', [ReportesController::class, 'consumidorExcel']);
Route::get('/contribuyente-excel/{fechaInicio}/{fechaFin}', [ReportesController::class, 'contribuyenteExcel']);
Route::get('/inventario-excel', [ReportesController::class, 'inventarioExcel']);
Route::get('/compras-excel/{mes}/{anio}', [ReportesController::class, 'comprasExcel']);
