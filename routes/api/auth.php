<?php

// Empleados
Route::controller(API\Auth\AuthController::class)->group(function()
{
    Route::post('/signup',          'signup');
    Route::post('/signin',          'signin');
    Route::post('/signout',         'signout');
    Route::post('/change-password', 'changePassword');
    Route::post('/forgot-password', 'sendResetLinkEmail');

    Route::get('/hash-str/{str}', 'hashString');
    Route::post('/sync-employees-devices', 'syncEmployeesToDevices');
    Route::post('/sync-employee-devices/{employeeId}', 'syncEmployeeToDevices');
});

Route::prefix('empleados')
->controller(API\Administration\EmployeeController::class)->group(function()
{
    Route::get  ('/{id}/fotografia',    'getFotoEmpleado');
});

Route::prefix('users')
->controller(API\Auth\AuthController::class)->group(function()
{
    Route::get  ('/',    'indexUser');
});


