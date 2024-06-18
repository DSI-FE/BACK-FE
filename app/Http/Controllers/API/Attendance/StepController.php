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
use App\Models\Attendance\Attachments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StepController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $rules = [
                'perPage' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'max:250'],
                'orderBy' => ['nullable', Rule::in(['id', 'name', 'description'])],
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

            $steps = Step::with(['permissionType', 'organizationalUnits'])
                ->where('att_steps.name', 'like', '%' . $search . '%')
                ->orWhere('att_steps.description', 'like', '%' . $search . '%')
                ->orderBy($orderBy, $orderDirection)
                ->paginate($perPage);

            $response = $steps->toArray();
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
                'name' => ['required', 'max:250', Rule::unique('att_steps', 'name')->where(function ($query) use ($request) {
                    return $query->where('name',  $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['nullable', 'max:1024'],
                'global' => ['required', 'boolean'],
                'managed_by_boss' => ['required', 'boolean'],
                'managed_by_supplicant' => ['required', 'boolean'],
                'correlative' => ['required', 'integer'],
                'hours_required' => ['nullable', 'integer'],
                'att_permission_type_id' => ['required', 'integer', 'exists:att_permission_types,id'],

                'employees' => ['nullable', 'array', 'filled'],
                'employees.*.adm_employee_id' => ['required', 'integer', 'exists:adm_employees,id'],

                'units' => ['nullable', 'array', 'filled'],
                'units.*.adm_organizational_unit_id' => ['required', 'integer', 'exists:adm_organizational_units,id'],

                'attachments' => ['nullable', 'array', 'filled'],
                'attachments.*.name' => ['required', 'max:250'],
                'attachments.*.mandatory' => ['required', 'boolean'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre para el Paso.',
                'name.max' => 'La longitud del Nombre para el Paso excede la longitud máxima.',
                'name.unique' => 'El Nombre para el Paso ya está utilizado en otro registro anterior.',
                'description.max' => 'El tamaño máximo para la Descripción fue excedida.',
                'global.required' => 'Falta la Clasificación de Global.',
                'global.boolean' => 'El formato de Global es irreconocible.',
                'managed_by_boss.required' => 'Falta la Clasificación de Administrado por Jefatura.',
                'managed_by_boss.boolean' => 'El formato de la Clasificación de Administrado por Jefatura es irreconocible.',
                'managed_by_supplicant.required' => 'Falta la Clasificación de Administrado por Solicitante.',
                'managed_by_supplicant.boolean' => 'El formato de la Clasificación de Administrado por Solicitante es irreconocible.',
                'correlative.required' => 'Falta Correlativo.',
                'correlative.integer' => 'Formato de Correlativo irreconocible.',
                'hours_required.integer' => 'Formato de Horas Requeridas irreconocible.',
                'att_permission_type_id.required' => 'Falta Identificador de Tipo de Permiso.',
                'att_permission_type_id.integer' => 'Formato de identificador de Tipo de Permiso irreconocible.',
                'att_permission_type_id.exists' => 'Identificador sin correspondencia con Tipo de Permiso.',

                'employees.array' => 'La lista de Empleados es irreconocible.',
                'employees.filled' => 'Se debe enviar al menos un elemento en la Lista de Empleados.',
                'employees.*.adm_employee_id.required' => 'Falta el Identificador de Empleado.',
                'employees.*.adm_employee_id.integer' => 'El identificador de Empleado es irreconocible.',
                'employees.*.adm_employee_id.exists' => 'Identificador de Empleados sin concordancia con Registros.',

                'units.array' => 'La Lista de Unidades es irreconocible.',
                'units.filled' => 'Se debe enviar al menos un elemento en la Lista de Unidades.',
                'units.*.adm_organizational_unit_id.required' => 'Falta el Identificador de Unidad Organizacional.',
                'units.*.adm_organizational_unit_id.integer' => 'El identificador de Unidad Organizacional es irreconocible.',
                'units.*.adm_organizational_unit_id.exists' => 'Identificador de Unidad sin concordancia con Registros.',

                'attachments.*.array' => 'La Lista de Archivos Adjuntos es irreconocible.',
                'attachments.*.filled' => 'Se debe enviar al menos un elemento en la Lista de Archivos Adjuntos.',
                'attachments.*.name.required' => 'Falta el nombre de Archivo Adjunto.',
                'attachments.*.name.max' => 'El tamaño máximo para el Nombre del Archivo Adjunto fue excedido.',
                'attachments.*.mandatory.required' => 'Falta la Clasificación si el Archivo Adjunto es requerido o no.',
                'attachments.*.mandatory.boolean' => 'La Clasificación si el Archivo Ajunto es irreconocible.',
            ];

            $request->validate($rules, $messages);

            $newStep = null;

            DB::transaction(function () use ($request, &$newStep) {
                $newData = [
                    'name' =>  $request->input('name'),
                    'description' =>  $request->input('description'),
                    'managed_by_boss' => $request->managed_by_boss,
                    'managed_by_supplicant' => $request->managed_by_supplicant,
                    'correlative' => $request->correlative,
                    'hours_required' => $request->hours_required,
                    'att_permission_type_id' => $request->att_permission_type_id,
                ];

                $newStep = Step::create($newData);

                if (isset($request->employees) && count($request->employees) > 0) {
                    $newStep->employees()->attach($request->employees);
                }

                if (isset($request->units) && count($request->units) > 0) {
                    $newStep->organizationalUnits()->attach($request->units);
                }

                if (isset($request->attachments) && count($request->attachments) > 0) {
                    foreach ($request->attachments as $idx => $attach) {
                        $attach['att_step_id'] = $newStep->id;
                        $attachments[] = Attachments::create($attach);
                    }
                }
            });

            return response()->json($newStep, 200);
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
                ['id' => ['required', 'integer', 'exists:att_steps,id']],
                [
                    'id.required' => 'Falta identificador de Paso.',
                    'id.integer' => 'Identificador de Paso irreconocible.',
                    'id.exists' => 'Paso solicitado sin coincidencia.',
                ]
            )->validate();

            $step = Step::findOrFail($validatedData['id']);
            $step->permissionType;
            $step->employees;
            $step->organizationalUnits;
            $step->attachments;

            return response()->json($step, 200);
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
    public function edit(Step $step)
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
                ['id' => ['required', 'integer', 'exists:att_steps,id']],
                [
                    'id.required' => 'Falta identificador de Paso.',
                    'id.integer' => 'Identificador de Paso irreconocible.',
                    'id.exists' => 'Paso solicitado sin coincidencia.',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('att_steps', 'name')->ignore($validatedData['id'])->where(function ($query) use ($request) {
                    return $query->where('name',  $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['nullable', 'max:1024'],
                'global' => ['required', 'boolean'],
                'managed_by_boss' => ['required', 'boolean'],
                'managed_by_supplicant' => ['required', 'boolean'],
                'correlative' => ['required', 'integer'],
                'hours_required' => ['nullable', 'integer'],
                'att_permission_type_id' => ['required', 'integer', 'exists:att_permission_types,id'],

                'employees' => ['nullable', 'array', 'filled'],
                'employees.*.adm_employee_id' => ['required', 'integer', 'exists:adm_employees,id'],

                'units' => ['nullable', 'array', 'filled'],
                'units.*.adm_organizational_unit_id' => ['required', 'integer', 'exists:adm_organizational_units,id'],

                'attachments' => ['nullable', 'array', 'filled'],
                'attachments.*.name' => ['required', 'max:250'],
                'attachments.*.mandatory' => ['required', 'boolean'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre para el Paso.',
                'name.max' => 'La longitud del Nombre para el Paso excede la longitud máxima.',
                'name.unique' => 'El Nombre para el Paso ya está utilizado en otro registro anterior.',
                'description.max' => 'El tamaño máximo para la Descripción fue excedida.',
                'global.required' => 'Falta la Clasificación de Global.',
                'global.boolean' => 'El formato de Global es irreconocible.',
                'managed_by_boss.required' => 'Falta la Clasificación de Administrado por Jefatura.',
                'managed_by_boss.boolean' => 'El formato de la Clasificación de Administrado por Jefatura es irreconocible.',
                'managed_by_supplicant.required' => 'Falta la Clasificación de Administrado por Solicitante.',
                'managed_by_supplicant.boolean' => 'El formato de la Clasificación de Administrado por Solicitante es irreconocible.',
                'correlative.required' => 'Falta Correlativo.',
                'correlative.integer' => 'Formato de Correlativo irreconocible.',
                'hours_required.integer' => 'Formato de Horas Requeridas irreconocible.',
                'att_permission_type_id.required' => 'Falta Identificador de Tipo de Permiso.',
                'att_permission_type_id.integer' => 'Formato de identificador de Tipo de Permiso irreconocible.',
                'att_permission_type_id.exists' => 'Identificador sin correspondencia con Tipo de Permiso.',

                'employees.array' => 'La lista de Empleados es irreconocible.',
                'employees.filled' => 'Se debe enviar al menos un elemento en la Lista de Empleados.',
                'employees.*.adm_employee_id.required' => 'Falta el Identificador de Empleado.',
                'employees.*.adm_employee_id.integer' => 'El identificador de Empleado es irreconocible.',
                'employees.*.adm_employee_id.exists' => 'Identificador de Empleados sin concordancia con Registros.',

                'units.array' => 'La Lista de Unidades es irreconocible.',
                'units.filled' => 'Se debe enviar al menos un elemento en la Lista de Unidades.',
                'units.*.adm_organizational_unit_id.required' => 'Falta el Identificador de Unidad Organizacional.',
                'units.*.adm_organizational_unit_id.integer' => 'El identificador de Unidad Organizacional es irreconocible.',
                'units.*.adm_organizational_unit_id.exists' => 'Identificador de Unidad sin concordancia con Registros.',

                'attachments.*.array' => 'La Lista de Archivos Adjuntos es irreconocible.',
                'attachments.*.filled' => 'Se debe enviar al menos un elemento en la Lista de Archivos Adjuntos.',
                'attachments.*.name.required' => 'Falta el nombre de Archivo Adjunto.',
                'attachments.*.name.max' => 'El tamaño máximo para el Nombre del Archivo Adjunto fue excedido.',
                'attachments.*.mandatory.required' => 'Falta la Clasificación si el Archivo Adjunto es requerido o no.',
                'attachments.*.mandatory.boolean' => 'La Clasificación si el Archivo Ajunto es irreconocible.',
            ];

            $request->validate($rules, $messages);

            $stepUpdated = NULL;

            DB::transaction(function () use ($request, $validatedData, &$stepUpdated) {
                $updateData = [
                    'name' =>  $request->input('name'),
                    'description' =>  $request->input('description'),
                    'managed_by_boss' => $request->managed_by_boss,
                    'managed_by_supplicant' => $request->managed_by_supplicant,
                    'correlative' => $request->correlative,
                    'hours_required' => $request->hours_required,
                    'att_permission_type_id' => $request->att_permission_type_id,
                ];

                $stepUpdated = Step::findOrFail($validatedData['id']);

                $stepUpdated->update($updateData);

                $stepUpdated->employees()->detach();

                if (isset($request->employees) && count($request->employees) > 0) {
                    $stepUpdated->employees->attach($request->employees);
                }

                $stepUpdated->organizationalUnits()->detach();

                if (isset($request->units) && count($request->units) > 0) {
                    $stepUpdated->organizationalUnits()->attach($request->units);
                }

                $actualAttachments = Attachments::where('att_step_id', $stepUpdated->id);

                if (count($actualAttachments)) {
                    foreach ($actualAttachments as $idx => $attach) {
                        $attach->delete();
                    }
                }

                if (isset($request->attachments) && count($request->attachments) > 0) {
                    foreach ($request->attachments as $idx => $attach) {
                        $attach['att_step_id'] = $stepUpdated->id;
                        $attachments[] = Attachments::create($attach);
                    }
                }
            });

            return response()->json($stepUpdated, 200);
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
                ['id' => ['required', 'integer', 'exists:att_steps,id']],
                [
                    'id.required' => 'Falta identificador de Paso.',
                    'id.integer' => 'Identificador de Paso irreconocible.',
                    'id.exists' => 'Paso solicitado sin coincidencia.',
                ]
            )->validate();

            $step = NULL;

            DB::transaction(function () use ($validatedData, &$step) {
                $step = Step::findOrFail($validatedData['id']);
                $step->delete();
                $step['status'] = 'deleted';
            });

            return response()->json($step, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

/**
 * agregar relación con unidades organizacionales de muchos a muchos
 */
