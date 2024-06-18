<?php

use App\Http\Controllers\API\Directory\DirectoryController;
use App\Http\Controllers\API\Directory\ContactController;

Route::prefix('directories')
->controller(API\Directory\DirectoryController::class)
->group(function () {
    Route::get('employee', 'indexEmployee');
    Route::get('public', 'indexPublic');
    Route::get('tester', 'tester');
});
Route::resource('directories', API\Directory\DirectoryController::class);

Route::prefix('contacts')
->controller(API\Directory\ContactController::class)
->group(function () {
    Route::get('index-by-directory-with-image/{directoryId}', 'indexByDirectoryWithImage');
});
Route::resource('contacts', API\Directory\ContactController::class);