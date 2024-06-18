<?php

Route::prefix('empleados')
    ->controller(API\Administracion\EmpleadoController::class)->group(function () {
        Route::get('/{id}/fotografia',    'getFotoEmpleado');
        Route::get('/pagination',         'indexPagination');
    });
Route::resource('empleados', API\Administracion\EmpleadoController::class)->only(['index', 'show']);

// Unidades Organizacionales
Route::prefix('unidades-organizacionales')
    ->controller(API\Administracion\UnidadOrganizacionalController::class)
    ->group(function () {
    });
Route::resource('unidades-organizacionales', API\Administracion\UnidadOrganizacionalController::class)->only(['index', 'show']);

// Cargos Funcionales
Route::controller(API\Administracion\CargoFuncionalController::class)
    ->prefix('cargos-funcionales')
    ->group(function () {
        Route::get('/{id}/unidad',                'unidad');
        Route::get('/{id}/cargos-empleados',      'cargosEmpleados');
    });
Route::resource('cargos-funcionales', API\Administracion\CargoFuncionalController::class)->only(['index', 'show']);
