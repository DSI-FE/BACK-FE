<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use App\Http\Resources\Administration\EmployeeResource;

use App\Models\Administration\Employee;
use App\Models\Administration\OrganizationalUnit;
use App\Models\General\GralConfiguration;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\PermissionType;
use App\Models\Attendance\PermissionRequest;

use App\Rules\DateInPastRule;
use App\Rules\DatePeriodRule;
use App\Rules\Attendance\HolidayOverlapRule;
use App\Rules\Attendance\MinutesPerRequestRule;



use Carbon\Carbon;
use Carbon\CarbonPeriod;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;


class PermissionRequestController extends Controller
{

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_UNPROCESSABLE_ENTITY = 422;

    public static $MAX_DATES_IN_PAST;

    public function __construct()
    {
        self::$MAX_DATES_IN_PAST = GralConfiguration::where('identifier', 'permission_days')->first()->value;
    }

    public function index()
    {
        return response()->json(PermissionRequest::all(), 200);
    }

    public function indexByEmployeeAndState( $employeeId, $state )
    {
        try {
            $data = [];
            $employee = Employee::findOrFail( $employeeId )->load([
                'permissionRequests.permissionType',
                'permissionRequests' => function ( $query ) use ($state) {
                    if ( $state && ( $state != 0 && $state != 'null' ) ) {
                        $query->where( 'state', '=', $state );
                    }
                }
            ]);
            $permissionRequests = $employee->permissionRequests;
            foreach ($permissionRequests as $key => $permissionRequest) {
                $data[$key]['id'] = $permissionRequest->id;
                $data[$key]['justification'] = $permissionRequest->justification;
                $data[$key]['adm_employee_id'] = $employee->id;
                $data[$key]['adm_employee_name'] = $employee->name.' '.$employee->lastname;
                $data[$key]['att_permission_type_id'] = $permissionRequest->att_permission_type_id;
                $data[$key]['att_permission_type_name'] = $permissionRequest->permissionType?->name;
                $data[$key]['state'] = $permissionRequest->state;
                $data[$key]['date_start'] = $permissionRequest->date_ini;
                $data[$key]['date_end'] = $permissionRequest->date_end;
                $data[$key]['time_start'] = $permissionRequest->time_ini;
                $data[$key]['time_end'] = $permissionRequest->time_end;
            }
            
            return response()->json($data,200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ha ocurrido un error al procesar la solicitud',
                'errors'=> $e->getMessage().' Line Number: '.$e->getLine()
            ], 500);
        }
    }
    
    public function indexByOrganizationalUnitAndState( $organizationalUnitId, $state )
    {
        try {
            $organizationalUnit = OrganizationalUnit::findOrFail($organizationalUnitId);
            $permissionRequests = $organizationalUnit->loadPermissionRequestsFromChildrensEmployees();
            return response()->json($permissionRequests,200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ha ocurrido un error al procesar la solicitud',
                'errors'=> $e->getMessage().' Line Number: '.$e->getLine()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Validate Request
        $validator = $this->validateStore( $request );
        if ( $validator->fails() ) {
            return response()->json( [ 'message' => 'Validation failed', 'errors' => $validator->errors() ], self::HTTP_UNPROCESSABLE_ENTITY );
        }
        return response()->json([ 'message' => 'Resource created successfully' ], self::HTTP_CREATED );
    }


    public function validateStore(Request $request)
    {

        $permissionType = PermissionType::find($request->input('att_permission_type_id'));
        $daysBeforeRequest = $permissionType && $permissionType->days_before_request ? $permissionType->days_before_request : self::$MAX_DATES_IN_PAST;
        $datesInPast = self::$MAX_DATES_IN_PAST <= $daysBeforeRequest ? self::$MAX_DATES_IN_PAST : $daysBeforeRequest;
        $daysPerRequest = $permissionType && $permissionType->days_per_request ? $permissionType->days_per_request : 365;
        $minutesPerRequest = $permissionType && $permissionType->minutes_per_request ? $permissionType->minutes_per_request : 365*480;
        $adjacentToHoliday = $permissionType?->adjacent_to_holiday;

        
        $rules = [
            'id' => [ 'integer', 'exists:att_permissions,id' ],
            'date_ini' => [
                'required',
                'date_format:Y-m-d',
                new DateInPastRule($datesInPast,'Fecha inicial no debe exceder '.$datesInPast.' días pasados'),
                new HolidayOverlapRule($adjacentToHoliday,null)
            ],
            'date_end' => [ 'required', 'date_format:Y-m-d', 'after_or_equal:date_ini', new DatePeriodRule($daysPerRequest, 'Cantidad de días solicitados no permitida') ],
            'time_ini' => [ 'required', 'date_format:H:i:s' ],
            'time_end' => [ 'required', 'date_format:H:i:s', new MinutesPerRequestRule($minutesPerRequest,null) ],
            'justification' => [ 'required', 'string' ],
            'state' => [ 'required', 'integer' ],
            'adm_employee_id' => [ 'required', 'integer', 'exists:adm_employees,id' ],
            'adm_employee_generated_id' => [ 'integer', 'exists:adm_employees,id' ],
            'adm_employee_boss_id' => [ 'integer', 'exists:adm_employees,id' ],
            'adm_employee_hr_id' => [ 'integer', 'exists:adm_employees,id' ],
            'att_permission_type_id' => [ 'required', 'integer', 'exists:att_permission_types,id' ],
            'boss_approved_at' => [ 'date_format:Y-m-d H:i:s' ],
            'hr_approved_at' => [ 'date_format:Y-m-d H:i:s' ],
        ];

        $messages = [

            'integer' => ':attribute debe ser un número entero válido',
            'exists' => ':attribute debe ser un valor registrado',
            'required' => 'Falta :attribute',
            'date_format' => ':attribute no cumple con el formato',
            'after_or_equal' => ':attribute debe ser mayor a :date',
            'string' => ':attribute debe ser una cadena de caracteres válida'
        ];

        $attributes = [
            'id' => 'Identificador',
            'date_ini' => 'Fecha inicial',
            'date_end' => 'Fecha final',
            'time_ini' => 'Hora inicial',
            'time_end' => 'Hora final',
            'justification' => 'Justificación',
            'state' => 'Estado',
            'adm_employee_id' => 'Empleado',
            'adm_employee_generated_id' => 'Empleado genera',
            'adm_employee_boss_id' => 'Empleado jefe',
            'adm_employee_hr_id' => 'Empleado TH',
            'att_permission_type_id' => 'Tipo de Permiso',
            'boss_approved_at' => 'Fecha aprobación jefe',
            'hr_approved_at' => 'Fecha aprobación talento humano'
        ];

        return Validator::make( $request->all(), $rules, $messages, $attributes );

    }

}