<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use App\Models\Administration\ParkingArea;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParkingAreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $parkingAreas = ParkingArea::select('id', 'name', 'description', 'access_card_required', 'active')
                ->withCount(['parkingAreaLevels as total_parkings' => function ($query) {
                    $query->selectRaw('coalesce(sum(parkings.parkings_count), 0)')
                        ->leftJoin('adm_parkings as parkings', 'adm_parking_area_levels.id', '=', 'parkings.adm_parking_area_level_id');
                }])
                ->withCount(['parkingAreaLevels as parkings_with_employee_count' => function ($query) {
                    $query->selectRaw('coalesce(sum(case when adm_parkings.adm_employee_id is not null then 1 else 0 end), 0)')
                        ->leftJoin('adm_parkings', 'adm_parking_area_levels.id', '=', 'adm_parkings.adm_parking_area_level_id')
                        ->whereNotNull('adm_parkings.adm_employee_id');
                }])
                ->withCount(['parkingAreaLevels as parkings_without_employee_count' => function ($query) {
                    $query->selectRaw('coalesce(sum(case when adm_parkings.adm_employee_id is null then 1 else 0 end), 0)')
                        ->leftJoin('adm_parkings', 'adm_parking_area_levels.id', '=', 'adm_parkings.adm_parking_area_level_id')
                        ->whereNull('adm_parkings.adm_employee_id');
                }])
                ->with(['parkingAreaLevels' => function ($query) {
                    $query->withCount('parkings');
                }])
                ->get();

            if ($parkingAreas->isNotEmpty()) {
               $parkingAreas->each(function ($parkingArea) {
                    $parkingArea->makeHidden('parkingAreaLevels');
                });
            }

            return response()->json($parkingAreas, 200);
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
                'name' => ['required', 'max:250', Rule::unique('adm_parking_areas','name')->whereNull('deleted_at')],
                'description' => ['nullable', 'max:1000'],
                'access_card_required' => ['required', Rule::in(['true', 'false'])],
            ];

            $messages = [
                'required' => 'Falta enviar el campo :attribute.',
                'max' => 'Se ha excedido la capacidad máxima para :attribute.',
                'unique' => ':attribute está asignado a un registro existente.',
                'in' => ':attribute está fuera de los parámetros permitidos.',
            ];

            $attributes = [
                'name' => 'el Nombre',
                'description' => 'la Descripción',
                'access_card_required' => 'el Acceso con Tarjeta es Requerido',
            ];

            $request->validate($rules, $messages, $attributes);

            $newParkingArea = [];

            DB::transaction(function () use ($request, &$newParkingArea) {
                $newParkingAreaData = [
                    'name' => $request->name,
                    'description' => $request->description,
                    'access_card_required' => $request->access_card_required == 'true' ? true : false,
                    'active' => true,
                ];

                $newParkingArea = ParkingArea::create($newParkingAreaData);
            });

            return response()->json($newParkingArea, 200);
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
                ['id' => ['required', 'integer', 'exists:adm_parking_areas,id']],
                [
                    'id.required' => 'Falta :attribute.',
                    'id.integer' => ':attribute irreconocible.',
                    'id.exists' => ':attribute solicitado sin coincidencia.',
                ],
                [ 'id' => 'Identificador de Área de Parqueo' ],
            )->validate();

            $parkingArea = ParkingArea::select('id', 'name', 'description', 'access_card_required', 'active')
                ->withCount(['parkingAreaLevels as total_parkings' => function ($query) {
                    $query->selectRaw('coalesce(sum(parkings.parkings_count), 0)')
                        ->leftJoin('adm_parkings as parkings', 'adm_parking_area_levels.id', '=', 'parkings.adm_parking_area_level_id');
                }])
                ->withCount(['parkingAreaLevels as parkings_with_employee_count' => function ($query) {
                    $query->selectRaw('coalesce(sum(case when adm_parkings.adm_employee_id is not null then 1 else 0 end), 0)')
                        ->leftJoin('adm_parkings', 'adm_parking_area_levels.id', '=', 'adm_parkings.adm_parking_area_level_id')
                        ->whereNotNull('adm_parkings.adm_employee_id');
                }])
                ->withCount(['parkingAreaLevels as parkings_without_employee_count' => function ($query) {
                    $query->selectRaw('coalesce(sum(case when adm_parkings.adm_employee_id is null then 1 else 0 end), 0)')
                        ->leftJoin('adm_parkings', 'adm_parking_area_levels.id', '=', 'adm_parkings.adm_parking_area_level_id')
                        ->whereNull('adm_parkings.adm_employee_id');
                }])
                ->with(['parkingAreaLevels' => function ($query) {
                    $query->withCount('parkings');
                }])
                ->findOrFail($validatedData['id']);

            return response()->json($parkingArea, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ParkingArea $parkingArea)
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
                'id' => ['required', 'integer', 'exists:adm_parking_areas,id'],
                'name' => ['required', 'max:250', Rule::unique('adm_parking_areas', 'name')->ignore($request->id)->whereNull('deleted_at')],
                'description' => ['nullable', 'max:1000'],
                'access_card_required' => ['required', Rule::in(['true', 'false'])],
                'active' => ['required', Rule::in(['true','false'])],
            ];

            $messages = [
                'required' => 'Falta enviar el campo :attribute.',
                'integer' => 'El formato d:attribute es irreconocible.',
                'exists' => ':attribute sin un registro relacionado',
                'max' => 'Se ha excedido la capacidad máxima para :attribute.',
                'unique' => ':attribute, está asignado a un registro existente.',
                'in' => ':attribute se encuentra fuera del rango de los datos esperados.',
            ];

            $attributes = [
                'id' => 'el Identificador del Área de Parqueo',
                'name' => 'el Nombre',
                'description' => 'la Descripción',
                'access_card_required' => 'el Acceso con Tarjeta es Requerido',
                'active' => 'el Estado del Área del Parqueo',
            ];

            $request->validate($rules, $messages, $attributes);

            $parkingArea = [];

            DB::transaction(function () use ($request, &$parkingArea) {
                $parkingArea = ParkingArea::findOrFail($request->id);

                $updateParkingAreaData = [
                    'name' => $request->name,
                    'description' => $request->description,
                    'access_card_required' => $request->access_card_required == 'true' ? true : false,
                    'active' => $request->active == 'true' ? true : false,
                ];

                $parkingArea->update($updateParkingAreaData);
            });

            return response()->json($parkingArea, 200);
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
                ['id' => ['required', 'integer', 'exists:adm_parking_areas,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Área de Parqueo',
                ]
            )->validate();

            $parkingArea = NULL;

            DB::transaction(function () use ($validatedData, &$parkingArea) {
                $parkingArea = ParkingArea::findOrFail($validatedData['id']);
                $parkingArea->delete();
                $parkingArea['status'] = 'deleted';
            });

            return response()->json($parkingArea, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeParkingAreas(int $id = null)
    {
        try {
            $commonQuery = ParkingArea::select('id', 'name', 'description', 'access_card_required', 'active')
                ->where('active', true);

            $parkingAreas = ($id !== null)
                ? $commonQuery->with('parkingAreaLevels')
                    ->findOrFail($id)
                : $commonQuery->with('parkingAreaLevels')
                    ->get();

            return response()->json($parkingAreas, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
