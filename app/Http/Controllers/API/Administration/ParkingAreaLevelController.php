<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use App\Models\Administration\ParkingAreaLevel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParkingAreaLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $parkingAreaLevels = ParkingAreaLevel::whereHas('parkingArea', function ($query) {
                    $query->where('active', true);
                })
                ->with('parkingArea')
                ->withCount([
                    'parkings',
                    'parkings AS parkings_used' => function ($query) {
                        $query->whereNotNull('adm_employee_id')->where('active', true);
                    },
                    'parkings AS parkings_unused' => function ($query) {
                        $query->whereNull('adm_employee_id')->where('active', true);
                    }
                ])->get();

            return response()->json($parkingAreaLevels, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id);

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
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
                'adm_parking_area_id' => ['required', 'integer', Rule::exists('adm_parking_areas', 'id')->whereNull('deleted_at')],
                'name' => ['required', 'max:250', Rule::unique('adm_parking_area_levels', 'name')->whereNull('deleted_at')->where('adm_parking_area_id', $request->adm_parking_area_id)],
                'description' => ['nullable', 'max:1000'],
            ];

            $messages = [
                'required' => 'Falta :attribute.',
                'integer' => 'El formato d:attribute es irreconocible.',
                'exists' => ':attribute sin concordancia con Áreas de parqueo existentes',
                'max' => 'Se ha excedido la longitud máxima d:attribute.',
                'unique' => ':attribute se encuentra asignado a otro Nivel.',
            ];

            $attributes = [
                'adm_parking_area_id' => 'el Identificador de Área de Parqueo',
                'name' => 'el Nombre',
                'description' => 'la Descripción',
            ];

            $request->validate($rules, $messages, $attributes);

            $newParkingAreaLevel = [];

            DB::transaction(function () use ($request, &$newParkingAreaLevel) {
                $newParkingAreaLevelData = [
                    'adm_parking_area_id' => $request->adm_parking_area_id,
                    'name' => $request->name,
                    'description' => $request->description,
                    'active' => true,
                ];

                $newParkingAreaLevel = ParkingAreaLevel::create($newParkingAreaLevelData);
            });

            return response()->json($newParkingAreaLevel, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

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
                ['id' => ['required', 'integer', 'exists:adm_parking_area_levels,id']],
                [
                    'id.required' => 'Falta :attribute.',
                    'id.integer' => ':attribute irreconocible.',
                    'id.exists' => ':attribute solicitado sin coincidencia.',
                ],
                ['id' => 'Identificador de Nivel de Área de Parqueo'],
            )->validate();

            $parkingAreaLevel = ParkingAreaLevel::withCount([
                'parkings',
                'parkings AS parkings_used' => function ($query) {
                    $query->whereNotNull('adm_employee_id')->where('active', true);
                },
                'parkings AS parkings_unused' => function ($query) {
                    $query->whereNull('adm_employee_id')->where('active', true);
                }
            ])->findOrFail($validatedData['id']);

            return response()->json($parkingAreaLevel, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        try {
            $rules = [
                'id' => ['required', 'integer', 'exists:adm_parking_area_levels,id'],
                'adm_parking_area_id' => ['required', 'integer', Rule::exists('adm_parking_areas', 'id')->whereNull('deleted_at')],
                'name' => ['required', 'max:250', Rule::unique('adm_parking_area_levels', 'name')->ignore($request->id)->whereNull('deleted_at')->where('adm_parking_area_id', $request->adm_parking_area_id)],
                'description' => ['nullable', 'max:1000'],
                'active' => ['required', Rule::in(['true', 'false'])]
            ];

            $messages = [
                'id.required|adm_parking_area_id.required|name.required|active.required' => 'Falta :attribute.',
                'id.integer|adm_parking_area_id.integer' => 'El formato d:attribute es irreconocible.',
                'id.exists|adm_parking_area_id.exists' => ':attribute se encuentra sin asociaciones con los Registros existentes.',
                'name.max|description:max' => 'Se ha excedido la longitud máxima d:attribute.',
                'name.unique' => ':attribute se encuentra asignado a otro Nivel.',
                'active.in' => 'Valor de :attribute, fuera de los parámetros esperados.'
            ];

            $attributes = [
                'id' => 'el Identificador de Nivel de Área de Parqueo',
                'adm_parking_area_id' => 'el Identificador de Área de Parqueo',
                'name' => 'el Nombre',
                'description' => 'la Descripción',
                'active' => 'el Estado del Nivel de Área de Parqueo',
            ];

            $request->validate($rules, $messages, $attributes);

            $parkingAreaLevel = NULL;

            DB::transaction(function () use ($request, &$parkingAreaLevel){
                $parkingAreaLevel = ParkingAreaLevel::findOrFail($request->id);

                $parkingAreaLevelData = [
                    'adm_parking_area_id' => $request->adm_parking_area_id,
                    'name' => $request->name,
                    'description' => $request->description,
                    'active' => $request->active == 'true' ? true : false,
                ];

                $parkingAreaLevel->update($parkingAreaLevelData);
            });

            return response()->json($parkingAreaLevel, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

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
                ['id' => ['required', 'integer', 'exists:adm_parking_area_levels,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Nivel de Área de Parqueo',
                ]
            )->validate();

            $parkingAreaLevel = NULL;

            DB::transaction(function () use ($validatedData, &$parkingAreaLevel) {
                $parkingAreaLevel = ParkingAreaLevel::findOrFail($validatedData['id']);
                $parkingAreaLevel->delete();
                $parkingAreaLevel['status'] = 'deleted';
            });

            return response()->json($parkingAreaLevel, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeParkingAreaLevels(int $id = null) {
        try {
            $commonQuery = ParkingAreaLevel::with(['parkingArea'])->withCount([
                'parkings',
                'parkings AS parkings_used' => function ($query) {
                    $query->whereNotNull('adm_employee_id')->where('active', true);
                },
                'parkings AS parkings_unused' => function ($query) {
                    $query->whereNull('adm_employee_id')->where('active', true);
                }
            ]);

            if ($id !== null) {
                $validatedData = Validator::make(
                    ['id' => $id],
                    ['id' => ['required', 'integer', 'exists:adm_parking_area_levels,id']],
                    [
                        'id.required' => 'Falta el :attribute.',
                        'id.integer' => 'El :attribute es irreconocible.',
                        'id.exists' => 'El :attribute enviado, sin coincidencia.',
                    ],
                    [
                        'id' => 'Identificador de Nivel de Área de Parqueo',
                    ]
                )->validate();

                $parkingAreaLevels = $commonQuery->with(['parkings' => function ($query) {
                        $query->where('active', true);
                    }])->findOrFail($validatedData['id']);
            } else {
                $parkingAreaLevels = $commonQuery->get();
            }

            return response()->json($parkingAreaLevels, 200)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}