<?php

use App\Http\Controllers\API\Institution\EntryController;


Route::prefix('entries')
->controller(API\Institution\EntryController::class)
->group(function () {
    Route::get('/index-with-files-by-type/type/{type}', 'indexWithFilesByType');
    Route::get('/index-with-files-by-type-and-subtype/type/{type}/subtype/{subtype}', 'indexWithFilesByTypeAndSubtype');
    Route::get('/show-active-with-files-by-type-and-subtype/type/{type}/subtype/{subtype}', 'showActiveWithFilesByTypeAndSubtype');
    
});
Route::resource('entries', EntryController::class);