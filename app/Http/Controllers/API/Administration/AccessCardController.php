<?php

namespace App\Http\Controllers\API\Administration;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Administration\AccessCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccessCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $accessCards = AccessCard::with(['parkingArea', 'employee:id,name,lastname,email'])->get();

            return response()->json($accessCards, 200);
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
                'adm_parking_area_id' => ['nullable', 'integer', 'exists:adm_parking_areas,id'],
                'adm_employee_id' => ['nullable', 'integer', 'exists:adm_employees,id'],
                'identifier' => ['required', 'max:250', Rule::unique('adm_access_cards', 'identifier')->whereNull('deleted_at')],
                'description' => ['nullable', 'max:1000'],
            ];

            $messages = [
                'integer' => 'El Formato enviado para :attribute es irreconocible.',
                'exists' => ':attribute sin coincidencias con los registros actuales.',
                'required' => 'Falta :attribute.',
                'max' => 'La longitud máxima para :attribute se ha excedido.',
                'unique' => 'Ya existe un registro que coincide con :attribute enviado.',
            ];

            $attributes = [
                'adm_parking_area_id' => 'el Identificador de Área de Parqueo',
                'adm_employee_id' => 'el Identificador de Colaborador',
                'identifier' => 'el Identificador de la Tarjeta de Acceso',
                'description' => 'la Descripción',
            ];

            $request->validate($rules, $messages, $attributes);

            $newAccessCard = [];

            DB::transaction(function () use ($request, &$newAccessCard) {
                $newAccessCardData = [
                    'adm_parking_area_id' => $request->adm_parking_area_id,
                    'adm_employee_id' => $request->adm_employee_id,
                    'identifier' => $request->identifier,
                    'description' => $request->description,
                    'active' => true,
                ];

                $newAccessCard = AccessCard::create($newAccessCardData);
            });

            return response()->json($newAccessCard, 200);
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
                ['id' => ['required', 'integer', 'exists:adm_access_cards,id']],
                [
                    'id.required' => 'Falta :attribute.',
                    'id.integer' => ':attribute irreconocible.',
                    'id.exists' => ':attribute solicitado sin coincidencia.',
                ],
                ['id' => 'Identificador de Tarjeta de Acceso'],
            )->validate();

            $accessCard = AccessCard::with(['parkingArea', 'employee:id,name,lastname,email'])->findOrFail($validatedData['id']);

            return response()->json($accessCard, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccessCard $accessCard)
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
                'id' => ['required', 'integer', 'exists:adm_access_cards,id'],
                'adm_parking_area_id' => ['nullable', 'integer', 'exists:adm_parking_areas,id'],
                'adm_employee_id' => ['nullable', 'integer', 'exists:adm_employees,id'],
                'identifier' => ['required', 'max:250', Rule::unique('adm_access_cards', 'identifier')->ignore($request->id)->whereNull('deleted_at')],
                'description' => ['nullable', 'max:1000'],
                'active' => ['required', Rule::in(['true', 'false'])],
            ];

            $messages = [
                'integer' => 'El Formato enviado para :attribute es irreconocible.',
                'exists' => ':attribute sin coincidencias con los registros actuales.',
                'required' => 'Falta :attribute.',
                'max' => 'La longitud máxima para :attribute se ha excedido.',
                'unique' => 'Ya existe un registro que coincide con :attribute enviado.',
                'in' => 'El :attribute enviado, sin coincidencia con los parámetros esperados.',
            ];

            $attributes = [
                'id' => 'el Identificador de la Tarjeta de Acceso',
                'adm_parking_area_id' => 'el Identificador de Área de Parqueo',
                'adm_employee_id' => 'el Identificador de Colaborador',
                'identifier' => 'el Identificador en la Tarjeta de Acceso',
                'description' => 'la Descripción',
                'active' => 'el Estado',
            ];

            $request->validate($rules, $messages, $attributes);

            $accessCard = null;

            DB::transaction(function () use ($request, &$accessCard) {
                $accessCard = AccessCard::findOrFail($request->id);

                $accessCardData = [
                    'adm_parking_area_id' => $request->adm_parking_area_id,
                    'adm_employee_id' => $request->adm_employee_id,
                    'identifier' => $request->identifier,
                    'description' => $request->description,
                    'active' => $request->active == 'true' ? true : false,
                ];

                $accessCard->update($accessCardData);
            });

            return response()->json($accessCard, 200);
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
                ['id' => ['required', 'integer', 'exists:adm_access_cards,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de la Tarjeta de Acceso',
                ]
            )->validated();

            $accessCard = null;

            DB::transaction(function () use ($validatedData, &$accessCard) {
                $accessCard = AccessCard::findOrFail($validatedData['id']);
                $accessCard->delete();
                $accessCard['status'] = 'deleted';
            });

            return response()->json($accessCard, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeAccessCards(int $id = null)
    {
        try {
            $commonQuery = AccessCard::with(['parkingArea', 'employee:id,name,lastname,email']);

            if ($id !== null) {
                $validatedData = Validator::make(
                    ['id' => $id],
                    ['id' => ['required', 'integer', 'exists:adm_access_cards,id']],
                    [
                        'id.required' => 'Falta el :attribute.',
                        'id.integer' => 'El :attribute es irreconocible.',
                        'id.exists' => 'El :attribute enviado, sin coincidencia.',
                    ],
                    [
                        'id' => 'Identificador de la Tarjeta de Acceso',
                    ]
                )->validate();

                $accessCards = $commonQuery->findOrFail($validatedData['id']);
            } else {
                $accessCards = $commonQuery->get();
            }

            return response()->json($accessCards, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
