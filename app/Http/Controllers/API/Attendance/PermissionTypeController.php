<?php

namespace App\Http\Controllers\API\Attendance;

use Exception;
use Illuminate\Http\Request;
use App\Models\Attendance\Step;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Administration\Employee;
use Illuminate\Support\Carbon;

use App\Models\Attendance\PermissionType;
use App\Models\General\GralConfiguration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;



class PermissionTypeController extends Controller
{
    public function index()
    {
        try {
            return response()->json(PermissionType::all(), 200);
        } catch ( Exception $e ) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $errors = null;
        $response = null;
        $httpCode = 200;

        $validator = $this->storeValidator($request);
        if(!$validator->fails())
        {
            try {
                $permissionType = PermissionType::updateOrCreate(
                    [ 'id'=>$request['id'] ],
                    [
                        'name' => $request['name'],
                        'description' => $request['description'],
                        'minutes_per_year' => $request['minutes_per_year'],
                        'minutes_per_month' => $request['minutes_per_month'],
                        'minutes_per_request' => $request['minutes_per_request'],
                        'requests_per_year' => $request['requests_per_year'],
                        'requests_per_month' => $request['requests_per_month'],
                        'days_per_request' => $request['days_per_request'],
                        'days_before_request' => $request['days_before_request'],
                        'discount_applies' => $request['discount_applies'],
                        'adjacent_to_holiday' => $request['adjacent_to_holiday'],
                        'later_days' => $request['later_days'],
                        'active' => $request['active'],
                        'dashboard_herarchy' => $request['dashboard_herarchy'],
                    ]
                );
                return response()->json($permissionType, 200);
            } catch ( Exception $e ) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

        } else {
            $errors['message'] = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);
    }
    
    public function show($id)
    {
        try {
            $permissionType = PermissionType::with('steps.organizationalUnits', 'steps.employees', 'steps.attachments')->find($id);
            return response()->json($permissionType, 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, int $id)
    {
    }

    public function destroy($id)
    {
        try {
            $permissionType = NULL;
            DB::transaction(function () use (&$permissionType,$id) {
                $permissionType = PermissionType::findOrFail($id);
                $permissionType->delete();
            });
            return response()->json($permissionType, 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'id' => [ 'integer','exists:att_permission_types,id' ],
            'name' => [ 'required', 'string', 'max:255', Rule::unique('att_permission_types')->ignore($request['id']) ],
            'description' => [ 'required', 'string' ],
            'minutes_per_year' => [ 'integer' ],
            'minutes_per_month' => [ 'integer' ],
            'minutes_per_request' => [ 'integer' ],
            'requests_per_year' => [ 'integer' ],
            'requests_per_month' => [ 'integer' ],
            'days_per_request' => [ 'integer' ],
            'days_before_request' => [ 'integer' ],
            'discount_applies' => [ 'required', 'boolean' ],
            'adjacent_to_holiday' => [ 'required', 'boolean' ],
            'later_days' => [ 'required', 'boolean' ],
            'active' => [ 'required', 'boolean' ],
            'dashboard_herarchy' => [ 'integer' ],
        ];
        
        $messages = [
            'id.integer' => 'Identificador debe ser un número entero válido',
            'id.exist' => 'Identificador debe encontrarse registrado',

            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe poseer máximo 255 caracteres',
            'name.unique' => 'Nombre ya se encuentra registrado',

            'description.required' => 'Descripción debe ser ingresada',
            'description.string' => 'Descripción debe ser una cadena de caracteres válida',

            'minutes_per_year.integer' => 'Minutos al año debe ser un número entero válido',
            'minutes_per_month.integer' => 'Minutos al mes debe ser un número entero válido',
            'minutes_per_request.integer' => 'Minutos por petición debe ser un número entero válido',
            'requests_per_year.integer' => 'Solicitudes al año debe ser un número entero válido',
            'requests_per_month.integer' => 'Solicitudes al mes debe ser un número entero válido',
            'days_per_request.integer' => 'Dias por solicitud debe ser un número entero válido',
            'days_before_request.integer' => 'Días antes de fecha actual debe ser un número entero válido',

            'discount_applies.required' => 'Aplica descuento debe ser ingresado',
            'discount_applies.boolean' => 'Aplica descuento debe ser un valor booleano válido',

            'adjacent_to_holiday.required' => 'Aplica en día continuo a feriado debe ser ingresado',
            'adjacent_to_holiday.boolean' => 'Aplica en día continuo a feriado debe ser un valor booleano válido',

            'later_days.required' => 'Aplica en días posteriores debe ser ingresado',
            'later_days.boolean' => 'Aplica en días posteriores debe ser un valor booleano válido',

            'active.required' => 'Activo debe ser ingresado',
            'active.boolean' => 'Activo debe ser un valor booleano válido',

            'dashboard_herarchy.integer' => 'Jerarquía en dashboard debe ser un número entero válido',
        ];

        return Validator::make($request->all(),$rules,$messages);
    }

    public function indexActive()
    {
        try {
            return response()->json(PermissionType::where('active',1)->get(), 200);
        } catch ( Exception $e ) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function showSimple(Request $request)
    {
        try {
            $data = [];
            $id = $request['permission_type_id'] ?? 1;
            $employeeId = $request['employee_id'] ?? Auth::user()->employee->id;

            $gralDays = GralConfiguration::select('value')->where('identifier','permission_days')->first();
            $permissionTypeRaw = PermissionType::select([
                'att_permission_types.id',
                'att_permission_types.name',
                'att_permission_types.description',

                'att_permission_types.minutes_per_request',
                'att_permission_types.days_per_request',

                'att_permission_types.minutes_per_year',
                'att_permission_types.minutes_per_month',

                'att_permission_types.requests_per_year',
                'att_permission_types.requests_per_month',

                'att_permission_types.days_before_request',
            ])
            ->where('att_permission_types.id',$id)
            ->where('att_permission_types.active',1)
            ->with([
                'steps',
                'employees' => function ($query) use ($employeeId) {
                    $query->where('adm_employees.id',$employeeId);
                }
            ])->first();
            
            $data['id'] = $permissionTypeRaw->id;
            $data['name'] = $permissionTypeRaw->name;
            $data['description'] = $permissionTypeRaw->description;
            $data['max_hours_per_request'] = $permissionTypeRaw->minutes_per_request;
            $data['max_days_per_request'] = $permissionTypeRaw->days_per_request;
            $data['max_hours_per_year'] = $permissionTypeRaw->minutes_per_year;
            $data['max_hours_per_month'] = $permissionTypeRaw->minutes_per_month;
            $data['max_requests_per_year'] = $permissionTypeRaw->requests_per_year;
            $data['max_requests_per_month'] = $permissionTypeRaw->requests_per_month;
            $data['permission_days'] = $permissionTypeRaw->days_before_request;
            
            // $data['non_business_days'] = $permissionTypeRaw->non_business_days;

            $data['steps'] = [];

            $steps = $permissionTypeRaw->steps;
            foreach ($steps as $key => $step) {
                $data['steps'][$key]['id'] = $step->id;
                $data['steps'][$key]['name'] = $step->name;
                $data['steps'][$key]['description'] = $step->description;
                $data['steps'][$key]['correlative'] = $step->correlative;
                $data['steps'][$key]['managed_by_boss'] = $step->managed_by_boss;
                $data['steps'][$key]['managed_by_supplicant'] = $step->managed_by_supplicant;
                $data['steps'][$key]['hours_required'] = $step->hours_required;

                $data['steps'][$key]['attachments'] = [];
                $attachments = $step->attachments;
                foreach ($attachments as $k => $attachment) {
                    $data['steps'][$key]['attachments'][$k]['id'] = $attachment->id;
                    $data['steps'][$key]['attachments'][$k]['name'] = $attachment->name;
                }
            }

            $emp = $permissionTypeRaw->employees[0];
            $data['gnral_days'] = intval($gralDays->value);
            $data['employee_permission_type'] = $emp->employeePermissionType;

            $employee = Employee::withActiveScheduleDays($employeeId,Carbon::now())->first();
            $schedule = $employee && count($employee->schedules) > 0 ? $employee->schedules[0] : NULL;
            $data['emp_schedule'] = $schedule;
            
            return response()->json($data, 200);
        } catch (Exception $e) {
            Log::error('Error: '.$e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}