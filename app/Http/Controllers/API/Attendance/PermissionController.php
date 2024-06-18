<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use App\Models\Administration\Employee;
use App\Models\Administration\EmployeeFunctionalPosition;
use App\Models\Administration\OrganizationalUnit;

use App\Models\Attendance\AttachmentPermissionFile;
use App\Models\Attendance\EmployeePermissionType;
use App\Models\Attendance\EmployeeSchedule;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Compensatory;

use App\Models\Attendance\PermissionType;
use App\Models\Attendance\Permission;
use App\Models\Attendance\PermissionComment;
use App\Models\Attendance\Step;
use App\Models\General\GralConfiguration;

use App\Notifications\PermissionNotification;
use App\Notifications\AttendanceNotification;

use App\Models\General\GralFile;

use Symfony\Component\HttpFoundation\Response;

use App\Helpers\DateHelper;

use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Jobs\SendEmailPermissionNotificationJob;

use Carbon\CarbonPeriod;

use Exception;
use File;
use Storage;
use TCPDF;
use TCPDF_COLORS;

class PermissionController extends Controller
{
    public function indexEmployee($employeeId,$state)
    {
        try {
            $data = [];
            $employeeId = $employeeId ?? Auth::user()->employee->id;
            $employee = Employee::where('id',$employeeId)->with(['permissions' => function ($query) use ($state) {
                if ($state && $state!=0 && $state!='null') {
                    $query->where('state', $state);
                }
            }])->first();
            $permissionsRaw = $employee->permissions; 
            
            foreach ($permissionsRaw as $key => $perm) {
                $status = $perm->state;
                $statusStr = '';
                switch ($status) {
                    case 0: $statusStr = ''; break;
                    case 1: $statusStr = 'Enviado'; break;
                    case 2: $statusStr = 'Aprobado por Jefe'; break;
                    case 3: $statusStr = 'Aprobado por TH'; break;
                    case 4: $statusStr = 'Observado'; break;
                    case 5: $statusStr = 'Rechazado'; break;
                    case 6: $statusStr = 'Cancelado'; break;
                    default: $statusStr = 'Sin Definir'; break;
                }
                $permissionType = $perm->employeePermissionType->permissionType;
                $data[$key]['employee_id'] = $employee->id;
                $data[$key]['name'] = $employee->name;
                $data[$key]['lastname'] = $employee->lastname;
                $data[$key]['perm_type_id'] = $permissionType->id;
                $data[$key]['perm_id'] = $perm->id;
                $data[$key]['type'] = $permissionType->name;
                $data[$key]['description'] = $perm->description;
                $data[$key]['status'] = $status;
                $data[$key]['statusStr'] = $statusStr;
                $data[$key]['date_start'] = $perm->date_ini;
                $data[$key]['date_end'] = $perm->date_end;
                $data[$key]['time_start'] = $perm->time_ini;
                $data[$key]['time_end'] = $perm->time_end;
            }
            return response()->json($data,200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id );
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number: '.$e->getLine()], 500);
        }
    }

    public function indexOrganizationalUnit($id,$state=null,$organizationalUnitId=null)
    {
        $data = [];

        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrenAndEmployees($orgUnitMain->code)->get();

        if($organizationalUnitId && $organizationalUnitId != 0 && $organizationalUnitId != 'null'){
            $orgUnits = $orgUnits->where('id',$organizationalUnitId);
        }

        $i=0;

        foreach ($orgUnits as $key => $orgUnit) {
            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositions;
            foreach ($empFunPositions as $keyEmp => $empFunPos) {
                $employee = $empFunPos->employee;

                $employee = Employee::where('id',$employee->id)->with(['permissions' => function ($query) use ($state) {
                    if ($state && $state!=0 && $state!='null') {
                        $query->where('state', $state);
                    }
                }])->first();
                $permissionsRaw = $employee->permissions;
                foreach ($permissionsRaw as $key => $perm) {
                    $status = $perm->state;
                    $statusStr = '';
                    switch ($status) {
                        case 0: $statusStr = ''; break;
                        case 1: $statusStr = 'Enviado'; break;
                        case 2: $statusStr = 'Aprobado por Jefe'; break;
                        case 3: $statusStr = 'Aprobado por TH'; break;
                        case 4: $statusStr = 'Observado'; break;
                        case 5: $statusStr = 'Rechazado'; break;
                        case 6: $statusStr = 'Cancelado'; break;
                        default: $statusStr = 'Sin Definir'; break;
                    }
                    $permissionType = $perm->employeePermissionType->permissionType;
                    $data[$i]['employee_id'] = $employee->id;
                    $data[$i]['name'] = $employee->name;
                    $data[$i]['lastname'] = $employee->lastname;
                    $data[$i]['perm_type_id'] = $permissionType->id;
                    $data[$i]['perm_id'] = $perm->id;
                    $data[$i]['type'] = $permissionType->name;
                    $data[$i]['description'] = $perm->description;
                    $data[$i]['status'] = $status;
                    $data[$i]['statusStr'] = $statusStr;
                    $data[$i]['date_start'] = $perm->date_ini;
                    $data[$i]['date_end'] = $perm->date_end;
                    $data[$i]['time_start'] = $perm->time_ini;
                    $data[$i]['time_end'] = $perm->time_end;
                    $i++;
                }
                
            }
        }
        return response()->json($data, 200);
    }

    public function indexByState($state=null)
    {
        $data = [];
        
        $employees = Employee::with(['permissions' => function ($query) use ($state) {
            if ($state && $state!=0 && $state!='null') {
                $query->where('state', $state);
            }
        }])->get();
        $i=0;
        foreach ($employees as $keyEmp => $employee) {
            $permissionsRaw = $employee->permissions;
            foreach ($permissionsRaw as $key => $perm) {
                $status = $perm->state;
                $statusStr = '';
                switch ($status) {
                    case 0: $statusStr = ''; break;
                    case 1: $statusStr = 'Enviado'; break;
                    case 2: $statusStr = 'Aprobado por Jefe'; break;
                    case 3: $statusStr = 'Aprobado por TH'; break;
                    case 4: $statusStr = 'Observado'; break;
                    case 5: $statusStr = 'Rechazado'; break;
                    case 6: $statusStr = 'Cancelado'; break;
                    default: $statusStr = 'Sin Definir'; break;
                }
                $permissionType = $perm->employeePermissionType->permissionType;
                $data[$i]['employee_id'] = $employee->id;
                $data[$i]['name'] = $employee->name;
                $data[$i]['lastname'] = $employee->lastname;
                $data[$i]['perm_type_id'] = $permissionType->id;
                $data[$i]['perm_id'] = $perm->id;
                $data[$i]['type'] = $permissionType->name;
                $data[$i]['description'] = $perm->description;
                $data[$i]['status'] = $status;
                $data[$i]['statusStr'] = $statusStr;
                $data[$i]['date_start'] = $perm->date_ini;
                $data[$i]['date_end'] = $perm->date_end;
                $data[$i]['time_start'] = $perm->time_ini;
                $data[$i]['time_end'] = $perm->time_end;
                $i++;
            }
        }
        return response()->json($data, 200);
    }

    public function indexAll()
    {
        $data = [];
        $employees = Employee::all();
        $i=0;
        foreach ($employees as $keyEmp => $employee) {
            $permissionsRaw = $employee->permissions;
            foreach ($permissionsRaw as $key => $perm) {
                $status = $perm->state;
                $statusStr = '';
                switch ($status) {
                    case 0: $statusStr = ''; break;
                    case 1: $statusStr = 'Enviado'; break;
                    case 2: $statusStr = 'Aprobado por Jefe'; break;
                    case 3: $statusStr = 'Aprobado por TH'; break;
                    case 4: $statusStr = 'Observado'; break;
                    case 5: $statusStr = 'Rechazado'; break;
                    case 6: $statusStr = 'Cancelado'; break;
                    default: $statusStr = 'Sin Definir'; break;
                }
                $permissionType = $perm->employeePermissionType->permissionType;
                $data[$i]['employee_id'] = $employee->id;
                $data[$i]['name'] = $employee->name;
                $data[$i]['lastname'] = $employee->lastname;
                $data[$i]['perm_type_id'] = $permissionType->id;
                $data[$i]['perm_id'] = $perm->id;
                $data[$i]['type'] = $permissionType->name;
                $data[$i]['description'] = $perm->description;
                $data[$i]['status'] = $status;
                $data[$i]['statusStr'] = $statusStr;
                $data[$i]['date_start'] = $perm->date_ini;
                $data[$i]['date_end'] = $perm->date_end;
                $data[$i]['time_start'] = $perm->time_ini;
                $data[$i]['time_end'] = $perm->time_end;
                $i++;
            }
        }
        return response()->json($data, 200);
    }
    
    public function application(Request $request)
    {
        $errors = null;
        $response = null;
        $httpCode = 200;

        if ( !$this->validateRequest( $request, $errors, $httpCode ) ) {
            return response()->json( $errors, $httpCode );
        }
        
        // Permission and PermissionType
        $permission = $request->input('id') ? Permission::find( $request->input('id') ) : null;
        $permissionType = PermissionType::find( $request->input('att_permission_type_id') );
        $description = $request->input('description');
        $steps = $permissionType->steps;

        // Dates and Time
        $dateIni = Carbon::createFromFormat( 'Y-m-d', $request->input('date_ini') );
        $dateEnd = Carbon::createFromFormat( 'Y-m-d', $request->input('date_end') );
        $timeIni = Carbon::createFromFormat( 'H:i:s', $request->input('time_ini').':00' )->setDate( 2000, 1, 1 );
        $timeEnd = Carbon::createFromFormat( 'H:i:s', $request->input('time_end').':00' )->setDate( 2000, 1, 1 );

        // Attachment            
        $mandatoryAttachment = $request->file('attachment_mandatory');

        // Employee, FunctionalPosition, Organizational Unit and Boss
        $employee = Employee::findOrFail( $request->input('adm_employee_id') );
        $functionalPosition = EmployeeFunctionalPosition::where(
        [
            'adm_employee_id'=>$employee->id,
            'principal'=>1,
            'date_end'=>null,
            'active'=>1
        ])->with('functionalPosition.organizationalUnit')->first();
        $isBoss = $functionalPosition->functionalPosition->boss;
        $isMainBoss = $isBoss && $functionalPosition->functionalPosition->boss_hierarchy == 1 ? true : false ;
        $organizationalUnit = $functionalPosition->functionalPosition->organizationalUnit;
        if( $isBoss && $isMainBoss ) {
            $organizationalUnit = $functionalPosition->functionalPosition->organizationalUnit->organizationalUnitParent;
        }
        $employeeBoss = $organizationalUnit->activeBossEmployeePrincipalFunctionalPositions && count( $organizationalUnit->activeBossEmployeePrincipalFunctionalPositions ) > 0 ? $organizationalUnit->activeBossEmployeePrincipalFunctionalPositions[0]->employee : null;
        $employeePermissionTypeOriginal = EmployeePermissionType::
        where(
        [
            'adm_employee_id'=>$employee->id,
            'att_permission_type_id'=>$permissionType->id,
            'month'=>$dateIni->format('m')
        ])->first();
        // Variables for calculations
        $pass = true;
        $debug = config('app.debug');
        $availableMinutesOnMonth = 0;
        $availableRequestsOnMonth = 0;
        $availableMinutesOnYear = 0;
        $availableRequestsOnYear = 0;
        $usedEmployeePermissionTypes = [];

        if ( !$this->checkPermissions( $employee, $permission, $dateIni, $dateEnd, $timeIni, $timeEnd, $permissionType, $response, $httpCode ) ) {
            return response()->json( $response, $httpCode );
        }


        $period = CarbonPeriod::create( $dateIni, $dateEnd );
        foreach ( $period as $key => $date ) {

            $holiday = Holiday::actualVacation($date->format('Y-m-d'))->get();
            
            if ( !( count($holiday) > 0) && !$date->isWeekend()  ) {
                
                $employeePermissionType = EmployeePermissionType::
                where([
                    'adm_employee_id'=>$employee->id,
                    'att_permission_type_id'=>$permissionType->id,
                    'month'=>$date->format('m')
                ])->first();
                
                $usedEmployeePermissionTypes[$employeePermissionType->id]['id'] = $employeePermissionType->id;
                $usedEmployeePermissionTypes[$employeePermissionType->id]['used_minutes'] = $usedEmployeePermissionTypes[$employeePermissionType->id]['used_minutes'] ?? $employeePermissionType->used_minutes;
                $usedEmployeePermissionTypes[$employeePermissionType->id]['used_requests'] = $usedEmployeePermissionTypes[$employeePermissionType->id]['used_requests'] ?? $employeePermissionType->used_requests;

                $minutesPerMonth = $permissionType->minutes_per_month;
                $minutesPerYear = $permissionType->minutes_per_year;
                $requestsPerMonth = $permissionType->requests_per_month;
                $requestsPerYear = $permissionType->requests_per_year;

                $usedMinutesOnMonth = $usedEmployeePermissionTypes[$employeePermissionType->id]['used_minutes'] ?? 0 ;
                $availableMinutesOnMonth = $minutesPerMonth ? $minutesPerMonth - $usedMinutesOnMonth : ( $minutesPerYear ? $minutesPerYear  - $usedMinutesOnMonth : (10000)) ;

                $usedRequestsOnMonth = $usedEmployeePermissionTypes[$employeePermissionType->id]['used_requests'] ?? 0 ;
                $availableRequestsOnMonth = $requestsPerMonth ? $requestsPerMonth - $usedRequestsOnMonth : ( $requestsPerYear ? $requestsPerYear - $usedRequestsOnMonth : (10000)) ;


                if ( ( $availableMinutesOnMonth > 0 && $availableRequestsOnMonth > 0 ) || isset($permission->id) ) {
                    
                    $totalSum = EmployeePermissionType::getTotalSum($request['adm_employee_id'], $permissionType->id);

                    $usedMinutesOnYear = $totalSum->total_minutes;
                    $usedRequestsOnYear = $totalSum->total_requests;
                    
                    $availableMinutesOnYear = $minutesPerYear ? $minutesPerYear - $usedMinutesOnYear : 30000 ;
                    $availableRequestsOnYear = $requestsPerYear ? $requestsPerYear - $usedRequestsOnYear : 30000 ;

                    if ( ($availableMinutesOnYear > 0 && $availableRequestsOnYear > 0) || isset($permission->id) ) {
                        $requestedTimeToDate = 0;
                        if ( $dateIni->format('Y-m-d') === $dateEnd->format('Y-m-d') ) {
                            $requestedTimeToDate = $timeIni->diffInMinutes($timeEnd);
                        } else {
                            $employeeSchedule = Employee::
                            where('id',$employee->id)
                            ->with(['schedules' => function ($query) use ($date) {
                                $query
                                    ->where('adm_employee_att_schedule.active', 1)
                                    ->where('adm_employee_att_schedule.date_start', '<=', $date)
                                    ->where(function ($query) use ($date) {
                                        $query
                                            ->where('adm_employee_att_schedule.date_end', '>=', $date)
                                            ->orWhereNull('adm_employee_att_schedule.date_end');
                                    })
                                    ->with(['days' => function ($query) use ($date) {
                                        $query
                                            ->where('number', $date->dayOfWeek);
                                    }])
                                    ->orderBy('adm_employee_att_schedule.id', 'desc')->first();
                            }])->first();
                            $timeStartSchedule = $employeeSchedule->schedules[0]->days[0]->pivot->time_start;
                            $timeStartSchedule = Carbon::createFromFormat('H:i:s',$timeStartSchedule)->setDate(2000, 1, 1);
                            $timeEndSchedule = $employeeSchedule->schedules[0]->days[0]->pivot->time_end;
                            $timeEndSchedule = Carbon::createFromFormat('H:i:s',$timeEndSchedule)->setDate(2000, 1, 1);
                            if ( $date->format('Y-m-d') === $dateIni->format('Y-m-d') ) {
                                $requestedTimeToDate = $timeEndSchedule->diffInMinutes($timeIni);
                            } else if ( $date->format('Y-m-d') === $dateEnd->format('Y-m-d') ) {
                                $requestedTimeToDate = $timeEnd->diffInMinutes($timeStartSchedule);
                            } else {
                                $requestedTimeToDate = $timeEndSchedule->diffInMinutes($timeStartSchedule);
                            }
                        }

                        if ( $permissionType->id===14 ) {
                            if($this->getCompensatoriesTimeAvailableByEmployee($employee->id,$dateIni)>=$requestedTimeToDate) {
                                $usedEmployeePermissionTypes[$employeePermissionType->id]['used_minutes'] += $requestedTimeToDate ;
                            } else {
                                $pass=false;
                                $httpCode=400;
                                $response['message']['compensatories'][] = 'No cuenta con tiempo compensatorio suficiente para la fecha seleccionada';
                            }
                        } else {
                            $usedEmployeePermissionTypes[$employeePermissionType->id]['used_minutes'] += $requestedTimeToDate ;
                        }

                    } else {
                        $pass=false;
                        $httpCode=400;
                        $response['message']['available_time'][] = 'No cuenta con tiempo disponible para este tipo de permiso en la fecha seleccionada (anual)';
                    }
                } else {
                    $pass=false;
                    $httpCode=400;
                    $response['message']['available_time'][] = 'No cuenta con tiempo disponible para este tipo de permiso en la fecha seleccionada (mensual)';
                    break;
                }

            }

        }

        $stepZeroAttach = $steps[0]->attachments;
        if (count($stepZeroAttach)>0 && (!$mandatoryAttachment || $mandatoryAttachment === 'undefined')) {
            $pass=false;
            $httpCode=400;
            $response['message']['step_attach'][] = 'Debe cargar el comprobante obligatorio';
        }

        if ( $pass ) {

            $totalUsedMinutes = 0;

            $i = 0 ;
            foreach ( $usedEmployeePermissionTypes as $key => $usedEmployeePermissionType ) {
                
                $employeePermissionType = EmployeePermissionType::find($usedEmployeePermissionType['id']);
                $totalUsedMinutes += $usedEmployeePermissionType['used_minutes'] - $employeePermissionType->used_minutes;
                $employeePermissionType->used_minutes = $usedEmployeePermissionType['used_minutes'];
                if ( $i === 0 && !$permission ) { $employeePermissionType->used_requests += 1; }
                $employeePermissionType->save();
                $i++;
            }

            if ( $permissionType->id===14 ) {
                $this->setCompensatoriesTimeAvailableByEmployee($employee->id,$dateIni,$totalUsedMinutes);
            }

            $permission = Permission::updateOrCreate([
                'id' => $request->input('id')
            ],
            [
                'date_ini' => $dateIni,
                'date_end' => $dateEnd,
                'time_ini' => $timeIni,
                'time_end' => $timeEnd,
                'state' => 1,
                'description' => $description,
                'adm_employee_att_permission_type_id'=>$employeePermissionTypeOriginal->id
            ]);

            $permissionId = $permission->id;
            
            if ( $mandatoryAttachment ) {
                $stepZeroAttachName = $steps[0]->attachments[0]->name;
                $stepZeroAttachId = $steps[0]->attachments[0]->id;
                $mandatoryAttachmentName = $mandatoryAttachment->getClientOriginalName();
                $mandatoryAttachmentExtn = $mandatoryAttachment->getClientOriginalExtension();
                $mandatoryAttachmentRoute = 'public/attendances/permissions/'.$permissionType->id.'/'.$employee->id.'/'.$permissionId;

                $fileName   = strtolower($stepZeroAttachName.'_'.$permissionId.'.'.$mandatoryAttachmentExtn);
                Storage::putFileAs($mandatoryAttachmentRoute, $mandatoryAttachment, $fileName);

                $gralFile = GralFile::create([
                    'name' => $fileName,
                    'original_name' => $mandatoryAttachmentName,
                    'route' => $mandatoryAttachmentRoute
                ]);

                $attachmentPermissionFile = AttachmentPermissionFile::create([
                    'att_attachment_id' => $stepZeroAttachId,
                    'att_permission_id' => $permissionId,
                    'gral_file_id' => $gralFile->id
                ]);

            }

            $dateFormatted = '';
            if( $dateIni->format('Y-m-d') === $dateEnd->format('Y-m-d') ) {
                $dateFormatted = 'Para el <span class="bold">'.ucfirst($dateIni->isoFormat('dddd D/MMMM/Y')).'</span>';
            } else {
                $dateFormatted = 'Desde el <span class="bold">'.ucfirst($dateIni->isoFormat('dddd D/MMMM/Y')).'</span> hasta el <span class="bold">'. ucfirst($dateEnd->isoFormat('dddd D/MMMM/Y')).'</span>';
            }
            $timeFormatted = 'En horario de <span class="bold">'.$timeIni->format('h:i A').'</span> a <span class="bold">'.$timeEnd->format('h:i A').'</span>';

            $notifiable = (new AnonymousNotifiable)->route('mail','mq21008@ues.edu.sv');
            // $notifiable = (new AnonymousNotifiable)->route('mail',$employee->email);
            $notifiable->notify(new AttendanceNotification(
                'Permiso Enviado',
                $employee->name,
                (
                    '<h2>¡Enviaste correctamente tu permiso!</h2>'.
                    '<h3>'.$permissionType->name.'</h3>'.
                    '<p>'.$dateFormatted.'</p>'.
                    '<p>'.$timeFormatted.'</p>'.
                    '<p> <span class="bold">Justificación:</span> '.$description.'</p>'.
                    '<br>'.
                    '<p>Hacé clic en el botón y se te redireccionará a la página donde puedes ver los permisos enviados</p>'
                ),
                'Ver Permisos',
                config('app.url')."/attendance/requests/permissions"
            ));

            if($employeeBoss){
                $notifiable2 = (new AnonymousNotifiable)->route('mail','mq21008@ues.edu.sv');
                // $notifiable2 = (new AnonymousNotifiable)->route('mail',$employeeBoss->email);
                $notifiable2->notify(new AttendanceNotification(
                    'Permiso de Colaborador Recibido',
                    $employeeBoss->name,
                    (
                        '<h2>Has recibido una solicitud de permiso de</h2>'.
                        '<h3>'.$employee->name.'</h3>'.
                        '<h3>'.$permissionType->name.'</h3>'.
                        '<p>'.$dateFormatted.'</p>'.
                        '<p>'.$timeFormatted.'</p>'.
                        '<p> <span class="bold">Justificación:</span> '.$description.'</p>'.
                        '<br>'.
                        '<p>Haz clic en el botón y se te redireccionará a la página donde puedes ver los permisos recibidos</p>'
                    ),
                    'Ver Permisos',
                    config('app.url')."/attendance/requests/permissions"
                ));
            }
        
        }
        
        return response()->json($response, $httpCode);

    }

    private function validateRequest(Request $request, &$errors, &$httpCode)
    {
        $validator = $this->storeValidator($request);
        if ($validator->fails()) {
            $errors['message'] = $validator->errors();
            $httpCode = 400;
            return false;
        }
        return true;
    }
    
    private function checkPermissions( $employee, $permission, $dateIni, $dateEnd, $timeIni, $timeEnd, $permissionType, &$response, &$httpCode )
    {
        if ( !$this->permissionsDaysValidator( $dateIni ) ) {
            $response['message']['gral_config_permissions_days'][] = 'Fecha solicitada pasa el límite permitido';
            $httpCode = 400;
            return false;
        }

        if ( !$this->daysBeforeRequestValidator( $dateIni, $permissionType->days_before_request ) ) {
            $response['message']['permission_type_day_before_request'][] = 'Fecha seleccionada no permitida por tipo de permiso';
            $httpCode = 400;
            return false;
        }

        if ( !$this->daysPerRequestValidator( $dateIni, $dateEnd, $permissionType->days_per_request ) ) {
            $httpCode=400;
            $response['message']['days_per_request'][] = 'Cantidad de días seleccionados no permitida';
            return false;
        }

        if ( !$this->minutesPerRequestValidator( $dateIni, $dateEnd, $timeIni, $timeEnd, $permissionType->minutes_per_request ) ) {
            $httpCode=400;
            $response['message']['minutes_per_request'][] = 'Tiempo solicitado no permitido';
            return false;
        }

        if ( !$this->checkOverlapsWithHoliday( $dateIni, $dateEnd, $timeIni, $timeEnd ) ) {
            $httpCode=400;
            $response['message']['holiday'][] = 'Fecha seleccionada no permitida por fecha festiva';
            return false;
        }
        
        if ( $this->checkOverlaps( $employee->id, $dateIni, $dateEnd, $timeIni, $timeEnd, $permission ) ) {
            $httpCode=400;
            $response['message']['permission_overlap'][] = 'Ya cuenta con un permiso en la fecha y hora seleccionada';
            return false;
        }

        return true;
    }

    public function downloadPdf($permissionId)
    {

        $permission = Permission::where('id',$permissionId)->with(['employeePermissionType.permissionType','permissionComments','steps','attachmentPermissionFile'])->first();
        
        $status = $permission->status;
        $statusStr = '';
        switch ($status) {
            case 0: $statusStr = ''; break;
            case 1: $statusStr = 'Enviado'; break;
            case 2: $statusStr = 'Aprobado por Jefe'; break;
            case 3: $statusStr = 'Aprobado por TH'; break;
            case 4: $statusStr = 'Observado'; break;
            case 5: $statusStr = 'Rechazado'; break;
            case 6: $statusStr = 'Cancelado'; break;
            default: $statusStr = 'Sin Definir'; break;
        }
        
        $employeeRaw = Employee::with([
            'user',
            'gender',
            'maritalStatus',
            'address',
            'functionalPositions' => function ($query) {
                $query->with('organizationalUnit');
            }
        ])->findOrFail($permission->employeePermissionType->adm_employee_id);

        $functionalPosition = $employeeRaw->functionalPositions->first();
        $organizationalUnit = $functionalPosition ? $functionalPosition->organizationalUnit : null;
        $photo = null;
        $boss = $organizationalUnit->activeBossEmployeePrincipalFunctionalPositions[0]->employee;
        $bossName = $boss->name.' '.$boss->lastname;
        try {
            $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employeeRaw->photo_route)));
        } catch (\Throwable $th) {}

        $employee = [
            'id' => $employeeRaw->id,
            'name' => $employeeRaw->name,
            'lastname' => $employeeRaw->lastname,
            'photo' => $photo,
            'phone' => $employeeRaw->phone,
            'email' => $employeeRaw->email,
            'functional_position' => $functionalPosition ? $functionalPosition->name : null,
            'organizational_unit' => $organizationalUnit ? $organizationalUnit->name : null,
            'organizational_unit_id' => $organizationalUnit ? $organizationalUnit->id : null
        ];

        $permissionCommentsRaw = $permission->permissionComments;
        $permissionComments = [];
        foreach ($permissionCommentsRaw as $key => $perCo) {
            $permissionComments[$key]['comment'] = $perCo->comment;
            $permissionComments[$key]['employee'] = $perCo->employee->name.' '.$perCo->employee->lastname;
        }

        $permissionAttachmentPermissionFilesRaw = $permission->attachmentPermissionFile;
        $attachments = [];
        foreach ($permissionAttachmentPermissionFilesRaw as $key => $papf) {

            $fileRoute = str_replace('public/', '', $papf->file->route);
            $filePath = $fileRoute.'/'.$papf->file->name;
            $attachments[$key]['id'] = $papf->file->id;
            $attachments[$key]['name'] = $papf->attachment->name;
            $attachments[$key]['file_name'] = $papf->file->name;
            $attachments[$key]['file_original_name'] = $papf->file->original_name;
            $attachments[$key]['route'] = $filePath;
        }

        $data['id'] = $permission->id;
        $data['employee'] = $employee;
        $data['employeePermissionType'] = $permission->employeePermissionType;
        $data['date_start'] = $permission->date_ini;
        $data['date_end'] = $permission->date_end;
        $data['created_at'] = $permission->created_at;
        $data['boss_approved_at'] = $permission->boss_approved_at;
        $data['rrhh_approved_at'] = $permission->rrhh_approved_at;
        $data['time_start'] = $permission->time_ini;
        $data['time_end'] = $permission->time_end;
        $data['description'] = $permission->description;
        $data['status'] = $permission->status;
        $data['status_str'] = $permission->statusStr;

        $data['permission_type_id'] = $permission->employeePermissionType->permissionType->id;
        $data['permission_type'] = $permission->employeePermissionType->permissionType->name;
        $data['permission_type_description'] = $permission->employeePermissionType->permissionType->description;
        $data['permission_comments'] = $permissionComments;
        $data['attachments'] = $attachments;

        $data['used_minutes'] = $permission->employeePermissionType->used_minutes;
        $data['used_requests'] = $permission->employeePermissionType->used_requests;

        // $data['max_hours_per_year'] = $permission->employeePermissionType->permissionType->max_hours_per_year;
        // $data['max_hours_per_month'] = $permission->employeePermissionType->permissionType->max_hours_per_month;
        // $data['max_requests_per_year'] = $permission->employeePermissionType->permissionType->max_requests_per_year;
        // $data['max_requests_per_month'] = $permission->employeePermissionType->permissionType->max_requests_per_month;
        
        $dataVista = [];

        $dataVista = [
            'nombre' => $data['employee']['name'].' '.$data['employee']['lastname'],
            'cargo' => $data['employee']['functional_position'],
            'tipo' => $data['permission_type'],
            
            'fecha_inicial' => DateHelper::localDate($data['date_start']),
            'fecha_final' => DateHelper::localDate($data['date_end']),
            'fecha_enviada' => $data['created_at'],
            'boss_approved_at' => $data['boss_approved_at'],
            'rrhh_approved_at' => $data['rrhh_approved_at'],
            'hora_inicial' =>  Carbon::createFromFormat('H:i:s',$data['time_start'])->format('h:i A'),
            'hora_final' =>  Carbon::createFromFormat('H:i:s',$data['time_end'])->format('h:i A'),
            'justificante' => $data['description'],
            'status' => $data['status_str'],

            'boss_name' => $bossName
          ];

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, "LETTER", true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Sistema Facturacion electronica');
        $pdf->SetTitle('Comprobante de Permiso');

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();
        // $pdf->ImageSVG($data['employee']['photo'], 180, 11, 20);
        // $pdf->Image($data['employee']['photo'], 20, 10, null, 22, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        $pdf->SetY(30);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 0, 'Solicitud de Permiso', 0, true, 'L', 0, '', 0, false, 'T', 'M');
        $pdf->ln(10);
        $pdf->SetFont('helvetica', '', 10);

        $html = view('attendances.comprobante', $dataVista)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        $carbon = Carbon::now();
        $output = $pdf->Output("Comprobantedepermiso-({$carbon->format("Y-m-d")}).pdf", 'S');


        // Set the response headers
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="example.pdf"',
        ];

        //============================================================+
        // END OF FILE
        //============================================================+
        // Return the PDF file as a response
        return response($output, Response::HTTP_OK, $headers);
    }
    
    public function store(Request $request)
    {
        //
    }

    public function checkOverlaps($employeeId, Carbon $dateStart, Carbon $dateEnd, Carbon $timeStart, Carbon $timeEnd, $permission = null)
    {
        try {
            $employee = Employee::findOrFail($employeeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return false;
        }

        $permissions = $employee->permissions;
        $permissionIds = [];

        foreach ($permissions as $key => $value) {
            if (!$permission || ($permission && $permission->id != $value->id)) {
                $permissionIds[] = $value->id;
            }
        }

        $overlap = Permission::where(function ($query) use ($dateStart, $dateEnd, $timeStart, $timeEnd) {
            $query->whereRaw('CONCAT(date_ini, " ", time_ini) < ? AND CONCAT(date_end, " ", time_end) > ?', [
                $dateEnd->toDateTimeString(),
                $dateStart->toDateTimeString(),
            ]);
        })->whereIn('id', $permissionIds)->exists();

        return $overlap;
    }
    
    public function setComment(Request $request)
    {
        if($request['observations'] && $request['observations'] != ''){
            PermissionComment::create([
                'comment'=>$request['observations'],
                'att_permission_id'=> $request['permission_id'],
                'adm_employee_id'=>Auth::user()->employee->id
            ]);
        }
    }

    public function manageState(Request $request)
    {
        $state = $request['state'];
        $isRRHH = $request['is_rrhh'] === 'true' ? true : false;
        $isBoss = $request['is_boss'] === 'true' ? true : false;
        $employeeApp = Employee::findOrFail($request['employee_id']);

        $employee = Employee::findOrFail(Auth::user()->employee->id);
        $permission = Permission::findOrFail($request['permission_id']);
        $permissionType = $permission->EmployeePermissionType->permissionType;

        $bossState = $permissionType->id === 20 && ($state === 1 || $state === '1') ? 3 : 2;
        $extraMsg = $bossState === 3 ? ' (Este permiso no requiere aprobación de Talento Humano)' : '';

        // dd($permissionType->id,$state,$bossState);

        $msg = '';

        switch($state) {
            case 1:
                if($isRRHH){
                    Permission::updateOrCreate(
                        [ 'id' => $request['permission_id'] ],
                        [ 'state' => 3, 'rrhh_approved_at'=>Carbon::now()]
                    );
                    if($request['observations'] && $request['observations'] != ''){
                        PermissionComment::create([
                            'comment'=>$request['observations'],
                            'att_permission_id'=> $request['permission_id'],
                            'adm_employee_id'=>Auth::user()->employee->id
                        ]);
                    }
                    $msg = 'Su solicitud de permiso ha sido APROBADA por Talento Humano';

                } else {
                    if ($isBoss) {
                        Permission::updateOrCreate(
                            [ 'id' => $request['permission_id'] ],
                            [ 'state' => $bossState, 'boss_approved_at'=>Carbon::now()]
                        );
                
                        if($request['observations'] && $request['observations'] != ''){
                            PermissionComment::create([
                                'comment'=>$request['observations'],
                                'att_permission_id'=> $request['permission_id'],
                                'adm_employee_id'=>Auth::user()->employee->id
                            ]);
                        }

                        $msg = 'Su solicitud de permiso ha sido APROBADA por su jefe';

                    }
                }
            break;
            case 2:
                Permission::updateOrCreate(
                    [ 'id' => $request['permission_id'] ],
                    [ 'state' => 4]
                );
                if($request['observations'] && $request['observations'] != ''){
                    PermissionComment::create([
                        'comment'=>$request['observations'],
                        'att_permission_id'=> $request['permission_id'],
                        'adm_employee_id'=>Auth::user()->employee->id
                    ]);
                }
                $msg = 'Su solicitud de permiso ha sido OBSERVADA';

            break;
            case 3:
                Permission::updateOrCreate(
                    [ 'id' => $request['permission_id'] ],
                    [ 'state' => 5]
                );
        
                if($request['observations'] && $request['observations'] != ''){
                    PermissionComment::create([
                        'comment'=>$request['observations'],
                        'att_permission_id'=> $request['permission_id'],
                        'adm_employee_id'=>Auth::user()->employee->id
                    ]);
                }
                $msg = 'Su solicitud de permiso ha sido DENEGADA';

            break;
        }

        // SendEmailPermissionNotificationJob::dispatch($employeeApp->email,$employeeApp,$employee,$permission,$msg);
        $dateIni = Carbon::createFromFormat('Y-m-d',$permission->date_ini);
        $dateEnd = Carbon::createFromFormat('Y-m-d',$permission->date_end);
        $timeIni = Carbon::createFromFormat('H:i:s',$permission->time_ini)->setDate(2000, 1, 1);
        $timeEnd = Carbon::createFromFormat('H:i:s',$permission->time_end)->setDate(2000, 1, 1);
        $dateFormatted = '';
        if( $dateIni->format('Y-m-d') === $dateEnd->format('Y-m-d') ) {
            $dateFormatted = 'Para el <span class="bold">'.ucfirst($dateIni->isoFormat('dddd D/MMMM/Y')).'</span>';
        } else {
            $dateFormatted = 'Desde el <span class="bold">'.ucfirst($dateIni->isoFormat('dddd D/MMMM/Y')).'</span> hasta el <span class="bold">'. ucfirst($dateEnd->isoFormat('dddd D/MMMM/Y')).'</span>';
        }
        $timeFormatted = 'En horario de <span class="bold">'.$timeIni->format('h:i A').'</span> a <span class="bold">'.$timeEnd->format('h:i A').'</span>';

        $notifiable = (new AnonymousNotifiable)->route('mail','mq21008@ues.edu.sv');
        // $notifiable = (new AnonymousNotifiable)->route('mail', $employeeApp->email,);
        $notifiable->notify(new AttendanceNotification(
            'Cambio de Estado en Permiso',
            $employeeApp->name,
            (
                '<h2>'.$msg.'</h2>'.
                '<h3>'.$permissionType->name.$extraMsg.'</h3>'.
                '<p>'.$dateFormatted.'</p>'.
                '<p>'.$timeFormatted.'</p>'.
                '<p> <span class="bold">Justificación:</span> '.$permission->description.'</p>'.
                '<br>'.
                '<p>Haz clic en el botón y se te redireccionará a la página donde puedes ver los permisos enviados</p>'
            ),
            'Ver Permisos',
            config('app.url')."/attendance/requests/permissions"
        ));


    }

    public function download($fileId)
    {
        $file = GralFile::find($fileId);
        $fileRoute = str_replace('public/', '', $file->route);
        $filePath = $fileRoute.'/'.$file->name;
        
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->download($filePath);
        }
        
        abort(404, 'File not found.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $data = [];
        $permission = Permission::where('id',$id)->with(['employeePermissionType.permissionType','permissionComments','steps','attachmentPermissionFile'])->first();
        
        $status = $permission->state;
        $statusStr = '';
        switch ($status) {
            case 0: $statusStr = ''; break;
            case 1: $statusStr = 'Enviado'; break;
            case 2: $statusStr = 'Aprobado por Jefe'; break;
            case 3: $statusStr = 'Aprobado por TH'; break;
            case 4: $statusStr = 'Observado'; break;
            case 5: $statusStr = 'Rechazado'; break;
            case 6: $statusStr = 'Cancelado'; break;
            default: $statusStr = 'Sin Definir'; break;
        }
        
        $employeeRaw = Employee::with([
            'user',
            'gender',
            'maritalStatus',
            'address',
            'functionalPositions' => function ($query) {
                $query->with('organizationalUnit');
            }
        ])->findOrFail($permission->employeePermissionType->adm_employee_id);

        $functionalPosition = $employeeRaw->functionalPositions->first();
        $organizationalUnit = $functionalPosition ? $functionalPosition->organizationalUnit : null;
        $photo = null;
        
        try {
            $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employeeRaw->photo_route)));
        } catch (\Throwable $th) {}

        $employee = [
            'id' => $employeeRaw->id,
            'name' => $employeeRaw->name,
            'lastname' => $employeeRaw->lastname,
            'photo' => $photo,
            'phone' => $employeeRaw->phone,
            'email' => $employeeRaw->email,
            'functional_position' => $functionalPosition ? $functionalPosition->name : null,
            'organizational_unit' => $organizationalUnit ? $organizationalUnit->name : null,
            'organizational_unit_id' => $organizationalUnit ? $organizationalUnit->id : null
        ];

        $permissionCommentsRaw = $permission->permissionComments;
        $permissionComments = [];
        foreach ($permissionCommentsRaw as $key => $perCo) {
            $permissionComments[$key]['comment'] = $perCo->comment;
            $permissionComments[$key]['employee'] = $perCo->employee->name.' '.$perCo->employee->lastname;
        }

        $permissionAttachmentPermissionFilesRaw = $permission->attachmentPermissionFile;
        $attachments = [];
        foreach ($permissionAttachmentPermissionFilesRaw as $key => $papf) {

            $fileRoute = str_replace('public/', '', $papf->file->route);
            $filePath = $fileRoute.'/'.$papf->file->name;
            $attachments[$key]['id'] = $papf->file->id;
            $attachments[$key]['name'] = $papf->attachment->name;
            $attachments[$key]['file_name'] = $papf->file->name;
            $attachments[$key]['file_original_name'] = $papf->file->original_name;
            $attachments[$key]['route'] = $filePath;
        }

        $data['id'] = $permission->id;
        $data['employee'] = $employee;
        $data['employeePermissionType'] = $permission->employeePermissionType;
        $data['date_start'] = $permission->date_ini;
        $data['date_end'] = $permission->date_end;
        $data['time_start'] = $permission->time_ini;
        $data['time_end'] = $permission->time_end;
        $data['description'] = $permission->description;
        $data['status'] = $permission->state;
        $data['status_str'] = $permission->statusStr;

        $data['permission_type_id'] = $permission->employeePermissionType->permissionType->id;
        $data['permission_type'] = $permission->employeePermissionType->permissionType->name;
        $data['permission_type_description'] = $permission->employeePermissionType->permissionType->description;
        $data['permission_comments'] = $permissionComments;
        $data['attachments'] = $attachments;
        


        $data['used_minutes'] = $permission->employeePermissionType->used_minutes;
        $data['used_requests'] = $permission->employeePermissionType->used_requests;

        $data['max_hours_per_year'] = $permission->employeePermissionType->permissionType->max_hours_per_year;
        $data['max_hours_per_month'] = $permission->employeePermissionType->permissionType->max_hours_per_month;
        $data['max_requests_per_year'] = $permission->employeePermissionType->permissionType->max_requests_per_year;
        $data['max_requests_per_month'] = $permission->employeePermissionType->permissionType->max_requests_per_month;
        

        return response()->json($data,200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {}

    public function permissionsDaysValidator($date)
    {
        $pass=true;
        $permissionsDays = GralConfiguration::where('identifier','permission_days')->first();
        if($permissionsDays && $permissionsDays->value>0){
            $dateMin = Carbon::now()->subDays(($permissionsDays->value)+1);
            if (!$date->gte($dateMin)) {
                $pass=false;
            }
        }
        return $pass;
    }

    public function getCompensatoriesTimeAvailableByEmployee($employeeId,$date)
    {
        $compensatories = Compensatory::available($employeeId,$date)->sum('time_available');
        return $compensatories;
    }

    public function setCompensatoriesTimeAvailableByEmployee($employeeId, $date, $totalUsedMinutes) {
        $compensatories = Compensatory::available($employeeId, $date)->get();
        
        foreach($compensatories as $compensatory) {
            // If the current compensatory's time_available is less than or equal to totalUsedMinutes,
            // subtract the time_available from totalUsedMinutes and set time_available to 0.
            if($compensatory->time_available <= $totalUsedMinutes) {
                $totalUsedMinutes -= $compensatory->time_available;
                $compensatory->time_available = 0;
            } else {
                // If the current compensatory's time_available is greater than totalUsedMinutes,
                // subtract totalUsedMinutes from time_available and set totalUsedMinutes to 0.
                $compensatory->time_available -= $totalUsedMinutes;
                $totalUsedMinutes = 0;
            }
    
            // Save the updated compensatory record
            $compensatory->save();
    
            // If totalUsedMinutes is 0, we can break out of the loop.
            if($totalUsedMinutes == 0) {
                break;
            }
        }
    }
    
    public function daysBeforeRequestValidator($date,$daysBeforeRequest=null)
    {
        $pass=true;
        if( $daysBeforeRequest && $daysBeforeRequest > 0 ){
            $dateMin = Carbon::now()->subDays($daysBeforeRequest+1);
            if (!$date->gte($dateMin)) {
                $pass=false;
            }
        }
        return $pass;
    }

    public function daysPerRequestValidator($dateIni,$dateEnd,$daysPerRequest=null)
    {
        $pass=true;
        if( $daysPerRequest && $daysPerRequest > 0 && ($dateEnd->diffInDays($dateIni)+1) > $daysPerRequest){
            $pass=false;
        }
        return $pass;
    }

    public function minutesPerRequestValidator($dateIni,$dateEnd,$timeIni,$timeEnd,$minutesPerRequest=null)
    {
        $pass=true;
        $totalMinutes = 0;
        $minutesPerDay = abs( $timeIni->diffInMinutes( $timeEnd ) );
        $period = CarbonPeriod::create( $dateIni, $dateEnd );
        foreach ( $period as $key => $date ) {
            $totalMinutes += $minutesPerDay;
        }
        if ( $minutesPerRequest && $minutesPerRequest > 0 && ( $totalMinutes > $minutesPerRequest ) ) {
            $pass=false;
        }
        return $pass;
    }
    
    public function checkOverlapsWithHoliday( $dateIni, $dateEnd) {
        $isValid = true;
        $holidays = Holiday::thisYear()->where('allow_adjacent_permissions',0)->get();

        foreach ($holidays as $holiday) {

            $startDateAdj = Carbon::parse($holiday->date_start)->subDay();
            $startDateAdjIsWeekend = $startDateAdj->isWeekend();
            while($startDateAdjIsWeekend) {
                $startDateAdj = $startDateAdj->subDay();
                $startDateAdjIsWeekend = $startDateAdj->isWeekend();
            }

            $endDateAdj = Carbon::parse($holiday->date_end)->addDay();
            $endDateAdjIsWeekend = $endDateAdj->isWeekend();
            while($endDateAdjIsWeekend) {
                $endDateAdj = $endDateAdj->addDay();
                $endDateAdjIsWeekend = $endDateAdj->isWeekend();
            }

            if (($startDateAdj->format('Y-m-d') <= $dateEnd->format('Y-m-d') && $endDateAdj->format('Y-m-d') >= $dateIni->format('Y-m-d')) ) {
                $isValid = false;
            }
        }

        return $isValid;

    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'id' => [ 'integer', 'exists:att_permissions,id' ],
            'date_ini' => [ 'required', 'date_format:Y-m-d' ],
            'date_end' => [ 'required', 'date_format:Y-m-d', 'after_or_equal:date_ini' ],
            'time_ini' => [ 'required', 'date_format:H:i' ],
            'time_end' => [ 'required', 'date_format:H:i' ],
            'description' => [ 'required', 'string' ],
            'boss_generated' => [ 'required', 'boolean' ],
            'boss_approved_at' => [ 'date_format:Y-m-d H:i:s' ],
            'rrhh_approved_at' => [ 'date_format:Y-m-d H:i:s' ],
            'state' => [ 'required', 'integer' ],
            'adm_employee_id' => [ 'required','integer','exists:adm_employees,id' ],
            'att_permission_type_id' => [ 'required','integer','exists:att_permission_types,id' ],
            // 'adm_employee_att_permission_type_id'=>[ 'required', 'exists:adm_employee_att_permission_type,id' ],
        ];

        $messages = [
            'id.integer' => 'Identificador debe ser un número entero válido',
            'id.exist' => 'Identificador debe encontrarse registrado',

            'date_ini.required' => 'Fecha inicial debe ser ingresada',
            'date_ini.date_format' => 'Fecha inicial debe cumplir con el formato Y-m-d',

            'date_end.required' => 'Fecha final debe ser ingresada',
            'date_end.date_format' => 'Fecha final debe cumplir con el formato Y-m-d',
            'date_end.after_or_equal' => 'Fecha final debe ser mayor a fecha inicial',

            'time_ini.required' => 'Hora inicial debe ser ingresada',
            'time_ini.date_format' => 'Hora inicial debe cumplir con el formato H:i',

            'time_end.required' => 'Hora final debe ser ingresada',
            'time_end.date_format' => 'Hora final debe cumplir con el formato H:i',

            'description.required' => 'Descripción debe ser ingresada',
            'description.string' => 'Descripción debe ser una cadena de caracteres válida',

            'boss_generated.required' => 'Generado por jefe debe ser ingresado',
            'boss_generated.boolean' => 'Generado por jefe debe ser un valor booleano válido',

            'boss_approved_at.date_format' => 'Fecha aprobación Jefe debe cumplir con el formato Y-m-d H:i:s',

            'rrhh_approved_at.date_format' => 'Fecha aprobación RRHH debe cumplir con el formato Y-m-d H:i:s',

            'state.required' => 'Estado debe ser ingresado',
            'state.integer' => 'Estado debe ser un valor entero válido',

            'adm_employee_id.required' => 'Empleado debe ser ingresado',
            'adm_employee_id.integer' => 'Empleado debe ser un valor entero',
            'adm_employee_id.exists' => 'Empleado debe estar registrado',

            'att_permission_type_id.required' => 'Tipo de permiso debe ser ingresado',
            'att_permission_type_id.integer' => 'Tipo de permiso debe ser un valor entero',
            'att_permission_type_id.exists' => 'Tipo de permiso debe estar registrado',

            // 'adm_employee_att_permission_type_id.required' => 'Relación con empleado debe ser ingresada',
            // 'adm_employee_att_permission_type_id.integer' => 'Relación con empleado debe ser un valor entero válido'
        ];

        return Validator::make($request->all(),$rules,$messages);
    }

}