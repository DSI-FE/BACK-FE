<?php

// Empleados

use App\Http\Controllers\API\Administration\AccessCardController;
use App\Http\Controllers\API\Administration\EmployeeController;
use App\Http\Controllers\API\Administration\DepartmentController;
use App\Http\Controllers\API\Administration\EmployeeBirthdayGreetingSentController;
use App\Http\Controllers\API\Administration\EmployeeRequestController;
use App\Http\Controllers\API\Administration\MunicipalityController;
use App\Http\Controllers\API\Administration\FunctionalPositionController;
use App\Http\Controllers\API\Administration\GenderController;
use App\Http\Controllers\API\Administration\MaritalStatusController;
use App\Http\Controllers\API\Administration\OrganizationalUnitController;
use App\Http\Controllers\API\Administration\OrganizationalUnitTypesController;
use App\Http\Controllers\API\Administration\PaymentVoucherController;
use App\Http\Controllers\API\Administration\EmployeeVehicleController;
use App\Http\Controllers\API\Administration\ParkingAreaController;
use App\Http\Controllers\API\Administration\ParkingAreaLevelController;
use App\Http\Controllers\API\Administration\ParkingController;

Route::prefix('employees')
->controller(EmployeeController::class)
->group(function () {
    Route::get('/directory/', 'indexDirectory');
    Route::get('/index-active/', 'indexActive');
    Route::get('/index-marking-required/', 'indexMarkingRequired');
    Route::get('/notifications',   'notifications');
    Route::get('/show-pic/{employeeId}', 'showPic');
    Route::get('/birthdays-between-dates/date-ini/{dateIni}/date-end/{dateEnd}', 'birthdaysBetweenDates');
    Route::get('/birthdays-in-month/date-ini/{dateIni}', 'birthdaysInMonth');
});
Route::resource('employees', EmployeeController::class)->only(['index', 'show', 'store','update']);
Route::get('active-employees', [EmployeeController::class, 'activeEmployees']);

Route::post('employees-unsubscribe/{id}', [EmployeeController::class, 'unsubscribeRequestEmployee']);
Route::match(['post'], 'employee-update/{id}', [EmployeeController::class, 'updateEmployeeInfo']);
Route::get('employee-image/{}', [EmployeeController::class, 'employeeId']);

Route::resource('departments', DepartmentController::class)->only(['index', 'show']);
Route::resource('municipalities', MunicipalityController::class)->only(['index', 'show']);

Route::prefix('functional-positions')
->controller(FunctionalPositionController::class)
->group(function () {
    Route::get('/active','activeFunctionalPositions');
    Route::get('/index-by-organizational-unit/{organizationalUnitId}','indexByOrganizationalUnit');
});
Route::resource('functional-positions', FunctionalPositionController::class);
Route::get('active-functional-positions/{id?}', [FunctionalPositionController::class, 'activeFunctionalPositions']);

// Route::resource('employees', AdmEmployeeController::class)->only(['index', 'show']);

Route::prefix('organizational-units')
->controller(OrganizationalUnitController::class)
->group(function () {
    Route::get('/{id}/childrens','getChildrens');
    Route::get('/{id}/childrens/employees','getEmployees');
    Route::get('/{id}/childrens/employees-discount','getEmployeesWithDiscount');
    Route::get('/{id}/childrens/employees-permission-types','getEmployeesPermissionTypes');
    Route::get('/{id}/employees','getEmployeesSimple');
    Route::get('/{id}/employees/bosses','getBossEmployees');
    Route::get('/index-simple','indexSimple');
});
Route::resource('organizational-units', OrganizationalUnitController::class)->only(['index', 'show']);
Route::get('active-organizational-units/{id?}', [OrganizationalUnitController::class, 'activeOrganizationalUnits']);

Route::get('organizational-units-simple', 'App\Http\Controllers\API\Administration\OrganizationalUnitController@indexSimple');

Route::resource('organizational-unit-types', OrganizationalUnitTypesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::get('active-organizational-unit-types/{id?}', [OrganizationalUnitTypesController::class, 'activeOrganizationalUnitTypes']);

Route::get('/now', function () {
    return response()->json([
        'today' => date_format(date_create(now()), 'Y-m-d'),
        'time' => date_format(date_create(now()), 'H:i:s')
    ]);
});

Route::resource('payment-vouchers', PaymentVoucherController::class)->except(['create', 'edit']);

// Route::post('markFromFile', [MarkerController:class, 'getFromFile'])->name('marksFromFile');

Route::resource('genders', GenderController::class)->only(['index']);
Route::get('active-genders', [GenderController::class, 'activeGenders']);

Route::resource('marital-statuses', MaritalStatusController::class)->only(['index']);
Route::get('active-marital-statuses', [MaritalStatusController::class, 'activeMaritalStatuses']);

Route::resource('employee-requests', EmployeeRequestController::class)->only(['show']);

Route::get('employee-birthdays/{id?}', [EmployeeController::class, 'birthdays']);

Route::resource('birthday-greetings', EmployeeBirthdayGreetingSentController::class)->only(['index']);

Route::resource('employee-vehicle', EmployeeVehicleController::class)->except(['create', 'edit']);

Route::resource('parking-areas', ParkingAreaController::class)->except(['create', 'edit']);
Route::get('parking-areas-active/{id?}', [ParkingAreaController::class, 'activeParkingAreas']);

Route::resource('parking-area-levels', ParkingAreaLevelController::class)->except(['create', 'edit']);
Route::get('parking-area-levels-active/{id?}', [ParkingAreaLevelController::class, 'activeParkingAreaLevels']);

Route::resource('parkings', ParkingController::class)->except(['create', 'edit']);
Route::get('parkings-active/area/{area}/level/{level}', [ParkingController::class, 'activeParkings']);

Route::resource('access-cards', AccessCardController::class)->except(['create', 'edit']);
Route::get('access-cards-active/{id?}', [AccessCardController::class, 'activeAccessCards']);
