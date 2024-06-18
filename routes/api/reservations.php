<?php

use App\Http\Controllers\API\Reservations\ReservationController;
use App\Http\Controllers\API\Reservations\ResourceController;
use App\Http\Controllers\API\Reservations\ResourceTypeController;

Route::resource('resource-types', ResourceTypeController::class)->except(['create', 'edit']);
Route::get('active-resource-types', [ResourceTypeController::class, 'activeResourceTypes']);
Route::get('active-resource-types-all', [ResourceTypeController::class, 'activeResourceTypesAll']);

Route::resource('resources', ResourceController::class)->except(['create', 'edit']);
Route::get('active-resources', [ResourceController::class, 'activeResources']);

Route::resource('bookings', ReservationController::class)->except(['create', 'edit']);
