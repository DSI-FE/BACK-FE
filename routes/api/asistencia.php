<?php

use App\Http\Controllers\API\Asistencia\AssScheduleController;

// Horario
Route::prefix('horarios')
    ->controller(API\Asistencia\HorarioController::class)->group(function () {
        Route::get('/pagination',         'indexPagination');
    });
Route::resource('horarios', API\Asistencia\HorarioController::class)->except(['create', 'edit']);

// Tipos de Permiso
Route::prefix('tipos-permisos')
    ->controller(API\Asistencia\TipoPermisoController::class)->group(function () {
        Route::get('/pagination',         'indexPagination');
    });
Route::resource('tipos-permisos', API\Asistencia\TipoPermisoController::class)->except(['create', 'edit']);

// Permisos
Route::prefix('permisos')
    ->controller(API\Asistencia\PermisoController::class)->group(function () {
        Route::get('/pagination',               'indexPagination');
        Route::get('/empleado/{empleadoId}',    'indexEmpleado');
    });
Route::resource('permisos', API\Asistencia\PermisoController::class)->except(['create', 'edit']);

Route::prefix('asuetos')
    ->controller(API\Asistencia\AsuetoExtraController::class)->group(function () {
        Route::get('/pagination',               'indexPagination');
    });
Route::resource('asuetos', API\Asistencia\AsuetoExtraController::class);

// Pasos Aprobacion
Route::prefix('pasos-aprobaciones')
    ->controller(API\Asistencia\PasoAprobacionController::class)->group(function () {
        Route::get('/pagination',               'indexPagination');
    });
Route::resource('pasos-aprobaciones', API\Asistencia\PasoAprobacionController::class);
Route::resource('asuetos', API\Asistencia\AsuetoExtraController::class)->except(['create', 'edit']);

Route::resource('schedules', AssScheduleController::class)->except(['create', 'edit']);
