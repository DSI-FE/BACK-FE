<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use App\Models\Administration\Parking;
use App\Models\Administration\ParkingAreaLevel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParkingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $parkings = Parking::whereHas('parkingAreaLevel', function ($query) {
                    $query->where('active', true);
                })->whereHas('parkingAreaLevel.parkingArea', function ($query) {
                    $query->where('active', true);
                })->with([
                    'parkingAreaLevel',
                    'parkingAreaLevel.parkingArea',
                    'employee:id,name,lastname,email,phone,photo_route_sm',
                ])->get();

            return response()->json($parkings, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id);

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
                'adm_parking_area_level_id' => ['required', 'integer', 'exists:adm_parking_area_levels,id'],
                'adm_parking_id' => ['nullable', 'integer', 'exists:adm_parkings,id'],
                'identifier' => ['required', 'max:250'],
                'description' => ['nullable', 'max:1000'],
                /** 'active' => ['required', Rule::in(['true', 'false'])], */
            ];

            $messages = [
                'required' => 'Falta :attribute.',
                'integer' => ':attribute posee un formato distinto al esperado.',
                'exists' => ':attribute sin coincidencia con los registros existentes.',
                'max' => ':attribute ha excedido la longitud máxima permitida.',
            ];

            $attributes = [
                'adm_parking_area_level_id' => 'el Identificador de Área de Parqueo',
                'adm_parking_id' => 'el Identificador del Parqueo Doble',
                'identifier' => 'el Identificador del Parqueo',
                'description' => 'la Descripción',
                // 'active' => 'el Estado',
            ];

            $request->validate($rules, $messages, $attributes);

            $newParking = [];

            DB::transaction(function () use ($request, &$newParking) {
                $newParkingData = [
                    'adm_parking_area_level_id' => $request->adm_parking_area_level_id,
                    'adm_parking_id' => $request->adm_parking_id,
                    'identifier' => $request->identifier,
                    'description' => $request->description,
                ];

                $newParking = Parking::create($newParkingData);

                if ($request->has('adm_parking_id')) {
                    $parkingBuddy = Parking::whereNull('adm_parking_id')->where('id', $request->adm_parking_id)->first();
                    if (!empty($parkingBuddy)) {
                        $parkingBuddy->update(['adm_parking_id' => $newParking->id]);
                    }
                }
            });

            return response()->json($newParking, 200);
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
    public function show($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_parkings,id']],
                [
                    'id.required' => 'Falta :attribute.',
                    'id.integer' => ':attribute irreconocible.',
                    'id.exists' => ':attribute solicitado sin coincidencia.',
                ],
                [ 'id' => 'Identificador del Parqueo' ],
            )->validate();

            $parking = Parking::with([
                    'parkingAreaLevel',
                    'employee:id,name,lastname,email,phone,photo_route_sm',
                    'parkingAreaLevel.parkingArea',
                    'parkingBuddy',
                ])
                ->findOrFail($validatedData['id']);

            return response()->json($parking, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Parking $parking)
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
                'adm_parking_area_level_id' => ['required', 'integer', 'exists:adm_parking_area_levels,id'],
                'adm_employee_id' => ['nullable', 'integer', 'exists:adm_employees,id'],
                'adm_parking_id' => ['nullable', 'integer', 'exists:adm_parkings,id'],
                'identifier' => ['required', 'max:250'],
                'description' => ['nullable', 'max:1000'],
                'active' => ['required', Rule::in(['true', 'false'])],
            ];

            $messages = [
                'required' => 'Falta :attribute.',
                'integer' => ':attribute posee un formato distinto al esperado.',
                'exists' => ':attribute sin coincidencia con los registros existentes.',
                'max' => ':attribute ha excedido la longitud máxima permitida.',
                'in' => ':attribute se encuentra fuera del rango de los datos esperados.',
            ];

            $attributes = [
                'adm_parking_area_level_id' => 'el Identificador de Área de Parqueo',
                'adm_employee_id' => 'el Identificador de Empleado',
                'adm_parking_id' => 'el Identificador del parqueo aledaño',
                'identifier' => 'el Identificador del Parqueo',
                'description' => 'la Descripción',
                'active' => 'el Estado',
            ];

            $request->validate($rules, $messages, $attributes);

            $parking = [];

            DB::transaction(function () use ($request, &$parking) {
                $parking = Parking::findOrFail($request->id);

                $updateParkingData = [
                    'adm_parking_area_level_id' => $request->adm_parking_area_level_id,
                    'adm_employee_id' => $request->adm_employee_id,
                    'adm_parking_id' => $request->adm_parking_id,
                    'identifier' => $request->identifier,
                    'description' => $request->description,
                    'active' => $request->active === 'true' ? true : false,
                ];

                $parking->update($updateParkingData);
            });

            return response()->json($parking, 200);
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
                ['id' => ['required', 'integer', 'exists:adm_parkings,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Parqueo',
                ]
            )->validate();

            $parking = NULL;

            DB::transaction(function () use ($validatedData, &$parking) {
                $parking = Parking::findOrFail($validatedData['id']);
                $parking->delete();
                $parking['status'] = 'deleted';
            });

            return response()->json($parking, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeParkings($area, int $level)
    {
        $user = Auth::user();

        try {
            $parkingLevel = ParkingAreaLevel::with(['parkings' => function ($query) use ($area) {
                $query->where('active', true);
                if ($area !== 'undefined' && intval($area)) {
                    $query->where('id', '!=', $area);
                }
            }])->findOrFail($level);

            return response()->json($parkingLevel, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . $user->id . '. Información enviada: ' . json_encode([$area, $level]));

            return response()->json(['message' => $e], 500);
        }
    }

}
