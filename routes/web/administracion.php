<?php

Route::middleware(['auth'])->group( function ()
{

    // // Empleados
    // Route::prefix('empleados')
    // ->controller(Administracion\EmpleadoController::class)->group(function()
    // {
    //     Route::get  ('/{id}/fotografia',    'getFotoEmpleado');
    // });
    // Route::resource('empleados',Administracion\EmpleadoController::class);

    // // Unidades Organizacionales
    // Route::prefix('unidades-organizacionales')
    // ->controller(Administracion\UnidadOrganizacionalController::class)
    // ->group(function()
    // {
    // });
    // Route::resource('unidades-organizacionales',Administracion\UnidadOrganizacionalController::class);

    // // Cargos Funcionales
    // Route::controller(Administracion\CargoFuncionalController::class)
    // ->prefix('cargos-funcionales')
    // ->group(function()
    // {
    //     Route::get  ('/{id}/unidad',                'unidad');
    //     Route::get  ('/{id}/cargos-empleados',      'cargosEmpleados');
    // });
    // Route::resource('cargos-funcionales',Administracion\CargoFuncionalController::class);
});