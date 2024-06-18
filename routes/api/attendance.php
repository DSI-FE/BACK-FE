<?php

use App\Http\Controllers\PythonTestController;
use App\Http\Controllers\API\Attendance\StepController;
use App\Http\Controllers\API\Attendance\HolidayController;
use App\Http\Controllers\API\Attendance\MarkingController;
use App\Http\Controllers\API\Attendance\PermissionTypeController;
use App\Http\Controllers\API\Attendance\PermissionController;
use App\Http\Controllers\API\Attendance\PermissionRequestController;


Route::prefix('permission-types')
->controller(API\Attendance\PermissionTypeController::class)
->group(function () {
    Route::get('/active', 'indexActive');
    Route::get('/show-simple', 'showSimple');
});
Route::resource('permission-types', PermissionTypeController::class)->except(['create', 'edit']);

Route::prefix('markings')
->controller(API\Attendance\MarkingController::class)
->group(function () {
        
    Route::post('/sync-devices/', 'syncDevices');
    Route::post('/set-period-data/', 'setPeriodData');

    Route::post('/set-employee-period-data/{date}/{employeeId}', 'setDateEmployeeData');

    Route::post('/sync-by-file/', 'syncByFiles');

    Route::get('/by-period/', 'getByPeriod');
    Route::get('/marks-from-employee/employee/{employeeId}/date/{date}', 'marksFromEmployee');

    Route::get('/by-date-employee/date/{date}/employee/{employeeId}', 'markingsByDateEmployee');

    
});
Route::resource('markings', API\Attendance\MarkingController::class)->only(['index']);

Route::prefix('discounts')
    ->controller(API\Attendance\DiscountController::class)
    ->group(function ()
    {
        Route::post('/calculate-period/', 'calculatePeriod');
        Route::post('/calculate-date-employee/{date}/{employeeId}', 'calculateDateEmployee');
        
        Route::get('/by-date', 'getByDate');
        
    });
Route::resource('discounts', API\Attendance\DiscountController::class)->only(['index']);

Route::prefix('permissions')
->controller(API\Attendance\PermissionController::class)
->group(function () {
    Route::get('/index-employee/{employeeId}/state/{state}', 'indexEmployee');
    Route::get('/index-organizational-unit/{id}/state/{state}/organizational-unit/{organizationalUnitId}', 'indexOrganizationalUnit');
    Route::get('/index-all', 'indexAll');
    Route::get('/index-by-state/{state}', 'indexByState');

    Route::get('/download/{fileId}', 'download');
    Route::get('/download-pdf/{permissionId}', 'downloadPdf');

    Route::post('/boss-approval', 'bossApproval');
    Route::post('/rrhh-approval', 'rrhhApproval');
    Route::post('/set-comment', 'setComment');
    Route::post('/manage-state', 'manageState');

    Route::post('/application', 'application');


});
Route::resource('permissions', PermissionController::class)->only(['store','show']);

Route::resource('steps', StepController::class)->except(['create', 'edit']);

Route::prefix('holidays')
->controller(API\Attendance\HolidayController::class)
->group(function () {
    Route::get('/index-simple', 'indexSimple');
    Route::get('/between-dates/date-ini/{dateIni}/date-end/{dateEnd}', 'indexBetweenDates');
    Route::get('/dates/date-ini/{dateIni}/date-end/{dateEnd}', 'datesWithHolidays');
});
Route::resource('holidays', HolidayController::class)->except(['create', 'edit']);

Route::get('active-schedules', 'App\Http\Controllers\API\Attendance\ScheduleController@activeSchedules');

Route::get('mark', 'App\Http\Controllers\API\Attendance\MarkingController@remoteMark');

Route::get('python', [PythonTestController::class, 'index'])->name('python');

Route::get('marks/{id?}', [MarkingController::class, 'getMarksFromDevices']);


Route::prefix('compensatories')
->controller(API\Attendance\CompensatoryController::class)
->group(function () {
    Route::get('/by-employee/{employeeId}', 'indexByEmployee');
    Route::get('/by-organizational-unit/{organizationalUnitId}', 'indexByOrganizationalUnit');
    Route::get('/all', 'indexAll');
    Route::get('/by-employee/{employeeId}/available-time', 'getTimeAvailableByEmployee');



    Route::post('/manage-state', 'manageState');
});
Route::resource('compensatories', API\Attendance\CompensatoryController::class);

Route::prefix('permission-requests')
->controller(API\Attendance\PermissionRequestController::class)
->group(function () {
    Route::get('/by-employee/{employeeId}/state/{state}', 'indexByEmployeeAndState');
    Route::get('/by-organizational-unit/{organizationalUnitId}/state/{state}', 'indexByOrganizationalUnitAndState');
});
Route::resource('permission-requests', PermissionRequestController::class)->except(['create', 'edit']);;
