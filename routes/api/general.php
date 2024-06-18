<?php

use App\Http\Controllers\API\General\GralConfigurationController;
use App\Http\Controllers\API\General\GralFileController;


Route::resource('configurations', GralConfigurationController::class)->except(['create', 'edit']);

Route::prefix('files')
->controller(API\General\GralFileController::class)
->group(function () {
    Route::get('/image/{id}', 'getImage');
});
