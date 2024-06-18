<?php

namespace App\Http\Controllers\API\Administration;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Administration\OrganizationalUnitTypes;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationalUnitTypesController extends Controller
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
                'orderBy' => ['nullable', Rule::in(['id', 'name'])],
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

            $unitTypes = OrganizationalUnitTypes::where('adm_organizational_unit_types.name', 'like', '%' . $search . '%')
                            ->orderBy($orderBy, $orderDirection)
                            ->paginate($perPage);

            return response()->json($unitTypes, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
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
                'name' => ['required', 'max:250', Rule::unique('adm_organizational_unit_types', 'name')->whereNull('deleted_at')],
                'staff' => ['required', Rule::in(['true', 'false'])],
            ];

            $messages = [
                'name.required|staff.required' => 'Falta :attribute del Tipo de Unidad Organizacional.',
                'name.max' => 'La longitud máxima para :attribute del Tipo de Unidad Organizacional ha sido excedida.',
                'name.unique' => ':attribute del Tipo de Unidad Organizacional enviado, ya se encuentra en uso.',
                'staff.in' => ':attribute se encuentra sin correspondencia con información esperada esperado.',
            ];

            $attributes = [
                'name' => 'el Nombre',
                'staff' => 'la Dependencia',
            ];

            $request->validate($rules, $messages, $attributes);

            $organizationalUnitTypeData = [
                'name' => $request->name,
                'staff' => $request->staff == 'true' ? true : false,
                'active' => true
            ];

            $newOrganizationalUnitType = OrganizationalUnitTypes::create($organizationalUnitTypeData);

            return response()->json($newOrganizationalUnitType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' en Línea ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required','integer','exists:adm_organizational_unit_types,id']],
                [
                    'id.required' => 'Falta identificador de Empleado.',
                    'id.integer' => 'Identificador de Empleado irreconocible.',
                    'id.exists' => 'Empleado solicitado sin coincidencia.',
                ]
            )->validate();

            $unitType = OrganizationalUnitTypes::with('organizational_units')->findOrFail($validatedData['id']);

            return response()->json($unitType, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);

            return response()->json(['message' => $e->getMessage() . ' | ' . $e->getLine() . ' - Ha ocurrido un error al procesar la solicitud.'], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OrganizationalUnitTypes $organizationalUnitTypes)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_organizational_unit_types,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Tipo de Unidad Organizacional',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('adm_organizational_unit_types', 'name')->ignore($validatedData['id'])->whereNull('deleted_at')],
                'staff' => ['required', Rule::in(['true', 'false'])],
                'active' => ['required', Rule::in(['true', 'false'])],
            ];

            $messages = [
                'name.required|staff.required|active.required' => 'Falta :attribute del Tipo de Unidad Organizacional.',
                'name.max' => 'La longitud máxima para :attribute del Tipo de Unidad Organizacional ha sido excedida.',
                'name.unique' => ':attribute del Tipo de Unidad Organizacional enviado, ya se encuentra en uso.',
                'staff.in|active.in' => ':attribute se encuentra sin correspondencia con la información esperada.',
            ];

            $attributes = [
                'name' => 'el Nombre',
                'staff' => 'la Dependencia',
                'active' => 'el Estado',
            ];

            $request->validate($rules, $messages, $attributes);

            $organizationalUnitTypeData = [
                'name' => $request->name,
                'staff' => $request->staff == 'true' ? true : false,
                'active' => $request->active == 'true' ? true : false,
            ];

            $updatedOrganizationalUnitType = NULL;

            DB::transaction(function () use ($validatedData, $organizationalUnitTypeData, &$updatedOrganizationalUnitType){
                $updatedOrganizationalUnitType = OrganizationalUnitTypes::findOrFail($validatedData['id']);
                $updatedOrganizationalUnitType->update($organizationalUnitTypeData);
            });

            return response()->json($updatedOrganizationalUnitType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' en línea ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_organizational_unit_types,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Tipo de Unidad Organizacional',
                ]
            )->validate();

            $organizationalUnitType = NULL;

            DB::transaction(function () use ($validatedData, &$organizationalUnitType) {
                $organizationalUnitType = OrganizationalUnitTypes::findOrFail($validatedData['id']);
                $organizationalUnitType->delete();
                $organizationalUnitType['status'] = 'deleted';
            });

            return response()->json($organizationalUnitType, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeOrganizationalUnitTypes()
    {
        try {
            $organizationalUnitTypes = OrganizationalUnitTypes::select('id', 'name', 'staff')
                ->where('active', true)
                ->get();

            return response()->json($organizationalUnitTypes, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' en línea ' . $e->getLine() . '. Por Usuario: ' . Auth::user()->id . '.');

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
