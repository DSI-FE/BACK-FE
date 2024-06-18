<?php

use App\Http\Controllers\API\Request\RequestCategoryController;
use App\Http\Controllers\API\Request\RequestCategoryStepController;
use App\Http\Controllers\API\Request\RequestTypeController;
use App\Http\Controllers\API\Request\RequestDetailAttachController;
use App\Http\Controllers\API\Request\RequestDetailController;
use App\Http\Controllers\API\Request\RequestResponseAttachController;
use App\Http\Controllers\API\Request\RequestResponseController;

Route::resource('request-types', RequestTypeController::class)->except(['create', 'edit']);
Route::get('request-types-active/{id?}', [RequestTypeController::class, 'activeRequestTypes']);

Route::resource('request-categories', RequestCategoryController::class)->except(['create', 'edit']);
Route::get('request-categories-active/{id?}', [RequestCategoryController::class, 'activeRequestCategories']);

Route::resource('request-category-steps', RequestCategoryStepController::class)->except(['create', 'edit']);
Route::get('request-category-steps-active/{id?}', [RequestCategoryStepController::class, 'activeRequestCategorySteps']);

Route::get('request-details/{id}', [RequestDetailController::class, 'index']);
Route::get('request-details-show/{id}', [RequestDetailController::class, 'show']);
Route::resource('request-details', RequestDetailController::class)->except(['index', 'show', 'create', 'edit']);

Route::resource('request-detail-attaches', RequestDetailAttachController::class)->only(['store', 'show', 'destroy']);

Route::get('request-responses/{id}', [RequestResponseController::class, 'index']);
Route::resource('request-responses', RequestResponseController::class)->except(['index', 'show', 'create', 'update', 'edit']);

Route::resource('request-response-attaches', RequestResponseAttachController::class)->only(['store', 'show', 'destroy']);

Route::get('by-types/{fullData?}', [RequestTypeController::class, 'myRequestTypes']);
Route::get('by-categories/{fullData?}', [RequestCategoryController::class, 'myRequestCategories']);
Route::get('assigned/{fullData?}', [RequestDetailController::class, 'myRequestAssigned']);
Route::get('created/{fullData?}', [RequestDetailController::class, 'myRequestCreated']);
