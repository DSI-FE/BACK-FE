<?php

// Images
Route::controller(API\ImageController::class)->group(function()
{
    Route::get('general/{imgName}',  'getGeneralImage');
    Route::get('foto-empleado/{empleadoFotoName}',  'getEmpleadoFoto');
});