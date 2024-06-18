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
    public function index(Request $request)
    {
        try {
            $rules = [
                'perPage' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'max:250'],
                'orderBy' => ['nullable', Rule::in(['id', 'name', 'description', 'internal_code', 'active'])],
                'orderDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'orderBy.in' => 'Valor de ordenamiento fuera de las opciones aceptables.',
                'orderDirection.in' => 'Valor de dirección de orden fuera de las opciones aceptables.',
            ];

            $request->validate($rules, $messages);

            $perPage = $request->query('perPage', 10);
            $search = $request->query('search', '');
            $orderBy = $request->query('orderBy', 'id');
            $orderDirection = $request->query('orderDirection', 'asc');

            $permissionTypes = PermissionType::where('att_permission_types.name', 'like', '%' . $search . '%')
                ->orWhere('att_permission_types.description', 'like', '%' . $search . '%')
                ->orWhere('att_permission_types.internal_code', 'like', '%' . $search . '%')
                ->orderBy($orderBy, $orderDirection)
                ->paginate($perPage);

            $response = $permissionTypes->toArray();
            $response['search'] = $search;
            $response['orderBy'] = $orderBy;
            $response['orderDirection'] = $orderDirection;

            return response()->json($response, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function indexSimple(Request $request)
    {
        try {
            return response()->json(PermissionType::where('active',1)->with(['steps.attachments'])->orderBy('att_permission_types.name','asc')->get(), 200);
        } catch (Exception $e) {
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

                'att_permission_types.max_hours_per_request',
                'att_permission_types.max_days_per_request',

                'att_permission_types.max_hours_per_year',
                'att_permission_types.max_hours_per_month',

                'att_permission_types.max_requests_per_year',
                'att_permission_types.max_requests_per_month',

                'att_permission_types.show_accumulated_hours_per_year',
                'att_permission_types.show_accumulated_hours_per_month',
                'att_permission_types.show_accumulated_requests_per_year',
                'att_permission_types.show_accumulated_requests_per_month',

                'att_permission_types.permission_days',

                'att_permission_types.non_business_days'
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
            $data['max_hours_per_request'] = $permissionTypeRaw->max_hours_per_request;
            $data['max_days_per_request'] = $permissionTypeRaw->max_days_per_request;
            $data['max_hours_per_year'] = $permissionTypeRaw->max_hours_per_year;
            $data['max_hours_per_month'] = $permissionTypeRaw->max_hours_per_month;
            $data['max_requests_per_year'] = $permissionTypeRaw->max_requests_per_year;
            $data['max_requests_per_month'] = $permissionTypeRaw->max_requests_per_month;
            $data['show_accumulated_hours_per_year'] = $permissionTypeRaw->show_accumulated_hours_per_year;
            $data['show_accumulated_hours_per_month'] = $permissionTypeRaw->show_accumulated_hours_per_month;
            $data['show_accumulated_requests_per_year'] = $permissionTypeRaw->show_accumulated_requests_per_year;
            $data['show_accumulated_requests_per_month'] = $permissionTypeRaw->show_accumulated_requests_per_month;
            $data['permission_days'] = $permissionTypeRaw->permission_days;
            
            $data['non_business_days'] = $permissionTypeRaw->non_business_days;

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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'max:250', Rule::unique('att_permission_types', 'name')->where(function ($query) use ($request) {
                    $query->where('name', $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['required', 'max:1000'],
                'internal_code' => ['required', 'max:250', Rule::unique('att_permission_types', 'internal_code')->where(function ($query) use ($request) {
                    return $query->where('internal_code', $request->input('internal_code'))->whereNull('deleted_at');
                })],
                'type' => ['required', 'integer', Rule::in([1, 2])],
                'leave_pay' => ['required', 'boolean'],
                'max_hours_per_year' => ['nullable', 'integer'],
                'max_hours_per_month' => ['nullable', 'integer'],
                'max_requests_per_year' => ['nullable', 'integer'],
                'max_requests_per_month' => ['nullable', 'integer'],
                'show_accumulated_hours_per_year' => ['required', 'boolean'],
                'show_accumulated_hours_per_month' => ['required', 'boolean'],
                'show_accumulated_requests_per_year' => ['required', 'boolean'],
                'show_accumulated_requests_per_month' => ['required', 'boolean'],
                'non_business_days' => ['required', 'boolean'],
                'static' => ['required', 'boolean'],
                /*'active' => ['nullable', 'boolean'],*/
            ];

            $messages = [
                'name.required' => 'Falta Nombre de Tipo de Permiso.',
                'name.max' => 'El Nombre de Tipo de Permiso excede la longitud máxima permitida.',
                // 'name.unique' => 'El Nombre de Tipo de Permiso enviado ya se encuentra en uso.',
                'description.required' => 'Falta la Descripción de Tipo de Permiso.',
                'description.max' => 'La Descripción de Tipo de Permiso excede la longitud máxima permitida.',
                'internal_code.required' => 'Falta el Código Interno.',
                'internal_code.max' => 'El Código Interno excede la longitud máxima permitida.',
                'internal_code.unique' => 'El Código Interno enviado ya se encuentra en uso.',
                'type.required' => 'Falta la clasificación de Tipo de Permiso.',
                'type.integer' => 'El Identificador de la clasificación de Tipo de Permiso es irreconocible.',
                'type.in' => 'La clasificación de Tipo Permiso está fuera del rango aceptable.',
                'leave_pay.required' => 'Falta si, el Tipo de Permiso procede o no con Goce de Sueldo.',
                'leave_pay.boolean' => 'El identificador de, si el Tipo de Permiso procede o no con Goce de Sueldo es irreconocible.',
                'max_hours_per_month.integer' => 'El valor para el Número de horas máximo por Mes es irreconocible.',
                'max_hours_per_year.integer' => 'El valor para el Número de horas máximo por Año es irreconocible.',
                'max_requests_per_month.integer' => 'El Número de Solicitudes de permisos Mensuales es irreconocible.',
                'max_requests_per_year.integer' => 'El Número de Solicitudes de permisos Anuales es irreconocible.',
                'show_accumulated_hours_per_year.required' => 'Falta el Clasificador para Mostrar las horas acumuladas por Año.',
                'show_accumulated_hours_per_year.boolean' => 'El Clasificador para Mostrar las horas acumuladas por Año es irreconocible.',
                'show_accumulated_hours_per_month.required' => 'Falta el Clasificador para Mostrar las horas acumuladas por Mes.',
                'show_accumulated_hours_per_month.boolean' => 'El Clasificador para Mostrar las horas acumuladas por Mes es irreconocible.',
                'show_accumulated_requests_per_year.required' => 'Falta el Clasificador para Mostrar las Solicitudes acumuladas por Año.',
                'show_accumulated_requests_per_year.boolean' => 'El Clasificador para Mostrar las Solicitudes acumuladas por Año es irreconocible.',
                'show_accumulated_requests_per_month.required' => 'Falta el Clasificador para Mostrar las Solicitudes acumuladas por Mes.',
                'show_accumulated_requests_per_month.boolean' => 'El Clasificador para Mostrar las Solicitudes acumuladas por Mes es irreconocible.',
                'non_business_days.integer' => 'Falta el Clasificador si es Día Laboral.',
                'non_business_days.boolean' => 'El Clasificador si es Día Laboral es irreconocible.',
                'static.integer' => 'Falta el Clasificador para Estático.',
                'static.boolean' => 'El Clasificador para Estático es irreconocible.',
                /*'active.boolean' => '',*/
            ];

            $request->validate($rules, $messages);

            $newData = [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'internal_code' => $request->input('internal_code'),
                'type' => $request->type,
                'leave_pay' => $request->leave_pay,
                'max_hours_per_year' => $request->max_hours_per_year ? $request->max_hours_per_year : 8760,
                'max_hours_per_month' => $request->max_hours_per_month ? $request->max_hours_per_month : 730,
                'max_requests_per_year' => $request->max_requests_per_year ? $request->max_requests_per_year : 100,
                'max_requests_per_month' => $request->max_requests_per_month ? $request->max_requests_per_month : 8,
                'show_accumulated_hours_per_year' => $request->show_accumulated_hours_per_year,
                'show_accumulated_hours_per_month' => $request->show_accumulated_hours_per_month,
                'show_accumulated_requests_per_year' => $request->show_accumulated_requests_per_year,
                'show_accumulated_requests_per_month' => $request->show_accumulated_requests_per_month,
                'non_business_days' => $request->non_business_days, //true,
                'static' => $request->static, //false,
                'active' => true,
            ];

            $newPermissionType = null;

            DB::transaction(function () use (&$newPermissionType, $newData) {
                $newPermissionType = PermissionType::create($newData);

                $defaultSteps['envio'] = Step::create([
                    'name' => 'Envío',
                    'global' => 1,
                    'managed_by_boss' => 0,
                    'managed_by_supplicant' => 1,
                    'correlative' => 1,
                    'att_permission_type_id' => $newPermissionType->id,
                ]);

                $defaultSteps['aprovacion'] = Step::create([
                    'name' => 'Aprobación de Jefatura',
                    'global' => 1,
                    'managed_by_boss' => 1,
                    'managed_by_supplicant' => 0,
                    'correlative' => 2,
                    'att_permission_type_id' => $newPermissionType->id,
                ]);

                $defaultSteps['rrhh'] = Step::create([
                    'name' => 'Aprobación de Recursos Humanos',
                    'global' => 1,
                    'managed_by_boss' => 0,
                    'managed_by_supplicant' => 0,
                    'correlative' => 100,
                    'att_permission_type_id' => $newPermissionType->id,
                ]);

                $employees = DB::table('adm_employee_adm_functional_position')
                    ->select('adm_employee_id')
                    ->where('adm_functional_position_id', 48)
                    ->get();

                if (count($employees) > 0) {
                    $employeesId = $employees->toArray();

                    for ($i = 0; $i < count($employeesId); $i++) {
                        $values[] = $employeesId[$i]->adm_employee_id;
                    }

                    $defaultSteps['rrhh']->employees()->attach($values);
                }
            });

            return response()->json($newPermissionType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_permission_types,id']],
                [
                    'id.required' => 'Falta identificador de Tipo de Permiso.',
                    'id.integer' => 'Identificador de Tipo de Permiso irreconocible.',
                    'id.exists' => 'Tipo de Permiso solicitado sin coincidencia.',
                ]
            )->validate();

            $permissionType = PermissionType::with('steps.organizationalUnits', 'steps.employees', 'steps.attachments')
                ->findOrFail($validatedData['id']);

            $permissionType->steps;

            return response()->json($permissionType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
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
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_permission_types,id']],
                [
                    'id.required' => 'Falta identificador de Tipo de Permiso.',
                    'id.integer' => 'Identificador de Tipo de Permiso irreconocible.',
                    'id.exists' => 'Tipo de Permiso solicitado sin coincidencia.',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('att_permission_types', 'name')->ignore($validatedData['id'])->where(function ($query) use ($request) {
                    $query->where('name', $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['required', 'max:1000'],
                'internal_code' => ['required', 'max:250', Rule::unique('att_permission_types', 'internal_code')->ignore($validatedData['id'])->where(function ($query) use ($request) {
                    return $query->where('internal_code', $request->input('internal_code'))->whereNull('deleted_at');
                })],
                'type' => ['required', 'integer', Rule::in([1, 2])],
                'leave_pay' => ['required', 'boolean'],
                'max_hours_per_year' => ['required', 'integer'],
                'max_hours_per_month' => ['required', 'integer'],
                'max_requests_per_year' => ['required', 'integer'],
                'max_requests_per_month' => ['required', 'integer'],
                'show_accumulated_hours_per_year' => ['required', 'boolean'],
                'show_accumulated_hours_per_month' => ['required', 'boolean'],
                'show_accumulated_requests_per_year' => ['required', 'boolean'],
                'show_accumulated_requests_per_month' => ['required', 'boolean'],
                'non_business_days' => ['required', 'boolean'],
                'static' => ['required', 'boolean'],
                'active' => ['required', 'boolean'],
            ];

            $messages = [
                'name.required' => 'Falta Nombre de Tipo de Permiso.',
                'name.max' => 'El Nombre de Tipo de Permiso excede la longitud máxima permitida.',
                // 'name.unique' => 'El Nombre de Tipo de Permiso enviado ya se encuentra en uso.',
                'description.required' => 'Falta la Descripción de Tipo de Permiso.',
                'description.max' => 'La Descripción de Tipo de Permiso excede la longitud máxima permitida.',
                'internal_code.required' => 'Falta el Código Interno.',
                'internal_code.max' => 'El Código Interno excede la longitud máxima permitida.',
                'internal_code.unique' => 'El Código Interno enviado ya se encuentra en uso.',
                'type.required' => 'Falta la clasificación de Tipo de Permiso.',
                'type.integer' => 'El Identificador de la clasificación de Tipo de Permiso es irreconocible.',
                'type.in' => 'La clasificación de Tipo Permiso está fuera del rango aceptable.',
                'leave_pay.required' => 'Falta si, el Tipo de Permiso procede o no con Goce de Sueldo.',
                'leave_pay.boolean' => 'El identificador de, si el Tipo de Permiso procede o no con Goce de Sueldo es irreconocible.',
                'max_hours_per_month.required' => 'Falta el valor para el Número de horas máximo por Mes.',
                'max_hours_per_month.integer' => 'El valor para el Número de horas máximo por Mes es irreconocible.',
                'max_hours_per_year.required' => 'Falta el valor para el Número de horas máximo por Año.',
                'max_hours_per_year.integer' => 'El valor para el Número de horas máximo por Año es irreconocible.',
                'max_requests_per_month.required' => 'Falta el Número de Solicitudes de permisos Mensuales.',
                'max_requests_per_month.integer' => 'El Número de Solicitudes de permisos Mensuales es irreconocible.',
                'max_requests_per_year.required' => 'Falta el Número de Solicitudes de permisos Anuales.',
                'max_requests_per_year.integer' => 'El Número de Solicitudes de permisos Anuales es irreconocible.',
                'show_accumulated_hours_per_year.required' => 'Falta el Clasificador para Mostrar las horas acumuladas por Año.',
                'show_accumulated_hours_per_year.boolean' => 'El Clasificador para Mostrar las horas acumuladas por Año es irreconocible.',
                'show_accumulated_hours_per_month.required' => 'Falta el Clasificador para Mostrar las horas acumuladas por Mes.',
                'show_accumulated_hours_per_month.boolean' => 'El Clasificador para Mostrar las horas acumuladas por Mes es irreconocible.',
                'show_accumulated_requests_per_year.required' => 'Falta el Clasificador para Mostrar las Solicitudes acumuladas por Año.',
                'show_accumulated_requests_per_year.boolean' => 'El Clasificador para Mostrar las Solicitudes acumuladas por Año es irreconocible.',
                'show_accumulated_requests_per_month.required' => 'Falta el Clasificador para Mostrar las Solicitudes acumuladas por Mes.',
                'show_accumulated_requests_per_month.boolean' => 'El Clasificador para Mostrar las Solicitudes acumuladas por Mes es irreconocible.',
                'non_business_days.required' => 'Falta el Clasificador si es Día Laboral.',
                'non_business_days.boolean' => 'El Clasificador si es Día Laboral es irreconocible.',
                'static.required' => 'Falta el Clasificador para Estático.',
                'static.boolean' => 'El Clasificador para Estático es irreconocible.',
                'active.required' => 'Falta el Clasificador si el Tipo de Permisos está Activo.',
                'active.boolean' => 'El Clasificador si el Tipo de Permisos está Activo es irreconocible.',
            ];

            $request->validate($rules, $messages);

            $updateData = [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'internal_code' => $request->input('internal_code'),
                'type' => $request->type,
                'leave_pay' => $request->leave_pay,
                'max_hours_per_year' => $request->max_hours_per_year,
                'max_hours_per_month' => $request->max_hours_per_month,
                'max_requests_per_year' => $request->max_requests_per_year,
                'max_requests_per_month' => $request->max_requests_per_month,
                'show_accumulated_hours_per_year' => $request->show_accumulated_hours_per_year,
                'show_accumulated_hours_per_month' => $request->show_accumulated_hours_per_month,
                'show_accumulated_requests_per_year' => $request->show_accumulated_requests_per_year,
                'show_accumulated_requests_per_month' => $request->show_accumulated_requests_per_month,
                'non_business_days' => $request->non_business_days,
                'static' => $request->static,
                'active' => $request->active,
            ];

            $permissionTypeUpdated = NULL;

            DB::transaction(function () use ($validatedData, $updateData, &$permissionTypeUpdated) {
                $permissionTypeUpdated = PermissionType::findOrFail($validatedData['id']);

                $permissionTypeUpdated->update($updateData);
            });

            return response()->json($permissionTypeUpdated, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: id: ' . json_encode($id) . ' | Request: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_permission_types,id']],
                [
                    'id.required' => 'Falta identificador de Tipo de Permiso.',
                    'id.integer' => 'Identificador de Tipo de Permiso irreconocible.',
                    'id.exists' => 'Tipo de Permiso solicitado sin coincidencia.',
                ]
            )->validate();

            $permissionType = NULL;

            DB::transaction(function () use ($validatedData, &$permissionType) {
                $permissionType = PermissionType::findOrFail($validatedData['id']);
                $permissionType->delete();
                $permissionType['status'] = 'deleted';
            });

            return response()->json($permissionType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id);

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}