<?php

namespace App\Http\Controllers\API\General;

use App\Helpers\StringsHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\General\GralConfiguration;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GralConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $gralConfigurations = [];


            $rules = [
                'search' => ['nullable', 'max:250'],
                'perPage' => ['nullable', 'integer', 'min:1'],
                'sort' => ['nullable'],
                'sort.order' => ['nullable', Rule::in(['id', 'name', 'lastname', 'email'])],
                'sort.key' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'sort.order.in' => 'El valor de ordenamiento es inválido.',
                'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
            ];

            $request->validate($rules, $messages);

            $search = StringsHelper::normalizarTexto($request->query('search', ''));
            $perPage = $request->query('perPage', 10);

            $sort = json_decode($request->input('sort'), true);
            $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
            $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

            if ($request->has('search') && !empty($request->search)) {
                $generalConfigurations = GralConfiguration::where('identifier', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            } else {
                $generalConfigurations = GralConfiguration::orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            }

            $response = $generalConfigurations->toArray();
            $response['search'] = $request->query('search', '');
            $response['sort'] = [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection
            ];

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
                'name' => ['required', 'max:250', Rule::unique('gral_configurations', 'name')->where(function ($query) use ($request) {
                    return $query->where('name',  $request->input('name'))->whereNull('deleted_at');
                })],
                'identifier' => ['required', 'max:250', 'regex:/^[a-zA-Z0-9\-_]+$/', Rule::unique('gral_configurations', 'identifier')->where(function ($query) use ($request) {
                    return $query->where('identifier',  $request->input('identifier'))->whereNull('deleted_at');
                })],
                'value' => ['required', 'max:2000'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre de la Configuración General.',
                'name.max' => 'Se ha excedido la longitud máxima para el Nombre de la Configuración General.',
                'name.unique' => 'El Nombre de la Configuración General enviado, ya está en uso.',
                'identifier.required' => 'Falta el Identificador de la Configuración General.',
                'identifier.max' => 'Se ha excedido la longitud máxima para el Identificador de la Configuración General.',
                'identifier.regex' => 'Existen caracteres inválidos en el Identificador de la Configuración General. Usar solo letras de a-z y/o A-Z y/o 0-9, guion medio (-) y/o guion bajo (_).',
                'identifier.unique' => 'El Identificador de la Configuración General enviado, ya está en uso.',
                'value.required' => 'Falta el Valor de la Configuración General.',
                'value.max' => 'Se ha excedido la longitud máxima para el Valor de la Configuración General.',
            ];

            $request->validate($rules, $messages);

            $newData = [
                'name' =>  $request->input('name'),
                'identifier' =>  $request->input('identifier'),
                'value' => $request->value,
            ];

            $newGralConfiguration = GralConfiguration::create($newData);

            return response()->json($newGralConfiguration, 200);
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
    public function show($identifier)
    {
        try {
            $validatedData = Validator::make(
                ['identifier' => $identifier],
                ['identifier' => ['required', 'max:250', 'regex:/^[a-zA-Z0-9\-_]+$/']],
                [
                    'identifier.required' => 'Falta el Identificador de la Configuración General.',
                    'identifier.max' => 'Se ha excedido la longitud máxima para el Identificador de la Configuración General.',
                    'identifier.regex' => 'Existen caracteres inválidos en el Identificador de la Configuración General. Usar solo letras de a-z y/o A-Z y/o 0-9, guion medio (-) y/o guion bajo (_).',
                ]
            )->validate();

            $gralConfiguration = GralConfiguration::where('identifier', '=', $validatedData['identifier'])->first();

            return response()->json($gralConfiguration, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($identifier));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($identifier));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GralConfiguration $gralConfiguration)
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
                ['id' => ['required', 'integer', 'exists:gral_configurations,id']],
                [
                    'id.required' => 'Falta el Identificador de la Configuración General.',
                    'id.integer' => 'Identificador de Configuración General irreconocible.',
                    'id.exists' => 'Configuración General sin coincidencia.',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('gral_configurations', 'name')->ignore($validatedData['id'])->where(function ($query) use ($request) {
                    return $query->where('name',  $request->input('name'))->whereNull('deleted_at');
                })],
                'identifier' => ['required', 'max:250', 'regex:/^[a-zA-Z0-9\-_]+$/', Rule::unique('gral_configurations', 'identifier')->ignore($validatedData['id'])->where(function ($query) use ($request) {
                    return $query->where('identifier',  $request->input('identifier'))->whereNull('deleted_at');
                })],
                'value' => ['required', 'max:2000'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre de la Configuración General.',
                'name.max' => 'Se ha excedido la longitud máxima para el Nombre de la Configuración General.',
                'name.unique' => 'El Nombre de la Configuración General enviado, ya está en uso.',
                'identifier.required' => 'Falta el Identificador de la Configuración General.',
                'identifier.max' => 'Se ha excedido la longitud máxima para el Identificador de la Configuración General.',
                'identifier.regex' => 'Existen caracteres inválidos en el Identificador de la Configuración General. Usar solo letras de a-z y/o A-Z y/o 0-9, guion medio (-) y/o guion bajo (_).',
                'identifier.unique' => 'El Identificador de la Configuración General enviado, ya está en uso.',
                'value.required' => 'Falta el Valor de la Configuración General.',
                'value.max' => 'Se ha excedido la longitud máxima para el Valor de la Configuración General.',
            ];

            $request->validate($rules, $messages);

            $updatedGralConfiguration = NULL;

            DB::transaction(function () use ($validatedData, $request, &$updatedGralConfiguration) {
                $updateData = [
                    'name' =>  $request->input('name'),
                    'identifier' =>  $request->input('identifier'),
                    'value' => $request->value,
                ];

                $updatedGralConfiguration = GralConfiguration::findOrFail($validatedData['id']);

                $updatedGralConfiguration->update($updateData);
            });

            return response()->json($updatedGralConfiguration);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

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
                ['id' => ['required', 'integer', 'exists:gral_configurations,id']],
                [
                    'id.required' => 'Falta el Identificador de la Configuración General.',
                    'id.integer' => 'Identificador de Configuración General irreconocible.',
                    'id.exists' => 'Configuración General sin coincidencia.',
                ]
            )->validate();

            $gralConfiguration = NULL;

            DB::transaction(function () use ($validatedData, &$gralConfiguration) {
                $gralConfiguration = GralConfiguration::findOrFail($validatedData['id']);
                $gralConfiguration->delete();
                $gralConfiguration['status'] = 'deleted';
            });

            return response()->json($gralConfiguration, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
