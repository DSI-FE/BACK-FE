<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Transport\TransportController;
use App\Http\Controllers\API\Transport\DepartmentController;
use App\Http\Controllers\API\Transport\MunicipalityController;
use App\Http\Controllers\API\Transport\DriverController; 
use App\Http\Controllers\API\Transport\VehicleController;
use App\Http\Controllers\API\Transport\VehicleAssignmentController; 
use App\Http\Controllers\API\Transport\TripTypeController;
use App\Http\Controllers\API\Transport\AssignmentController;
use App\Http\Controllers\API\Transport\VehicleTypeController;

Route::prefix('request')->group(function () {
    Route::post('/', [TransportController::class, 'store']);
});

Route::get('/requests', [TransportController::class, 'index']);
Route::get('/requestsAdmin', [TransportController::class, 'indexAdmin']);
Route::get('/requests-calendar', [TransportController::class, 'indexCalendar']);
Route::get('/requestBy/{id}', [TransportController::class, 'show']);
Route::patch('/requestUpd/{id}', [TransportController::class, 'update']); 
Route::delete ('/requestDel/{id}', [TransportController::class, 'delete']);
Route::put('/cancelTransport/{id}', [TransportController::class, 'cancel']); 
Route::put('/rejectTransport/{id}', [TransportController::class, 'reject']); 
Route::patch('/updateTransportCancellation/{id}', [TransportController::class, 'updateTransportCancellation']);
Route::put('/approveTransport/{id}', [TransportController::class, 'statusApprove']);


Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/department/{id}', [DepartmentController::class, 'show']);
Route::get('/municipalities', [MunicipalityController::class, 'index']);
Route::get('/municipality/{id}', [MunicipalityController::class, 'show']);
Route::get('/departments-with-municipalities', [DepartmentController::class, 'getDepartmentsWithMunicipalities']);
Route::get('/department/{id}/municipalities', [DepartmentController::class, 'getMunicipalitiesByDepartment']);

Route::get('/drivers', [DriverController::class, 'index']);
Route::get('/driver/{id}', 'API\Transport\DriverController@getDriverById');


Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehiclesAll', [VehicleController::class, 'indexAll']);
Route::post('/store-vehicle', [VehicleController::class, 'store']);
Route::get('/vehicleBy/{id}', [VehicleController::class, 'show']);
Route::patch('/vehicleUpd/{id}', [VehicleController::class, 'update']);
Route::delete('/vehicleDel/{id}', [VehicleController::class, 'delete']);

Route::post('/vehicle-store-assignments', [VehicleAssignmentController::class, 'store']);
Route::get('/vehicle-assignments', [VehicleAssignmentController::class, 'index']);
Route::get('/index-vehicle-assignments', [VehicleAssignmentController::class, 'indexAdmin']);
Route::get('/assignments-driver-vehicle/{id}', [VehicleAssignmentController::class, 'showAssignmentDetails']);
Route::get('/vehicle-assignments-all', [VehicleAssignmentController::class, 'showAll']);
Route::get('/vehicle-assignments-false', [VehicleAssignmentController::class, 'Assgnfalse']);
Route::get('/vehicle-show-assignments/{id}', [VehicleAssignmentController::class, 'show']);
Route::get('/showByDriver/{driverId}', [VehicleAssignmentController::class, 'showByDriver']);
Route::put('/vehicle-upd-assignments/{id}', [VehicleAssignmentController::class, 'update']);
Route::put('/update-vehicle-status', [VehicleAssignmentController::class, 'updateStatus']);
Route::put('/update-assignment-status', [VehicleAssignmentController::class, 'updateAssignmentStatus']);
Route::delete('/vehicle-del-assignments/{id}', [VehicleAssignmentController::class, 'delete']);

Route::post('/storeTripTypes', [TripTypeController::class, 'store']);
Route::get('/getTripTypes', [TripTypeController::class, 'index']);
Route::get('/getTripTypes/{id}', [TripTypeController::class, 'show']);
Route::put('/PutTripTypes/{id}', [TripTypeController::class, 'update']);
Route::delete('/delTripTypes/{id}', [TripTypeController::class, 'destroy']);

Route::post('/assignmentStore', [AssignmentController::class, 'store']);
Route::get('/assignmentIndex', [AssignmentController::class, 'index']);
Route::get('/assignments-array', [AssignmentController::class, 'indexArray']);
Route::get('/assignmentShowBy/{transport_id}', [AssignmentController::class, 'show']);
Route::get('/assignmentShowByDriver/{id}', [AssignmentController::class, 'showByDriver']);
Route::patch('/assignmentUpdBy/{transport_id}', [AssignmentController::class, 'update']);
Route::delete('/assignmentDelBy/{transport_id}', [AssignmentController::class, 'delete']);
Route::get('/assignmentobsAndCancel', [AssignmentController::class, 'obsAndCancel']);

Route::get('/vehicleTypes', [VehicleTypeController::class, 'index']);
Route::get('/vehicleType/{id}', [VehicleTypeController::class, 'show']);
Route::post('/vehicleType', [VehicleTypeController::class, 'store']);
Route::put('/vehicleType/{id}', [VehicleTypeController::class, 'update']);
Route::delete('/vehicleType/{id}', [VehicleTypeController::class, 'delete']);