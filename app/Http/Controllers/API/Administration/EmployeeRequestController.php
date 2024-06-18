<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use App\Models\Administration\EmployeeRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Collection;

class EmployeeRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        dd($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_requests,employee_id_affected']],
                [
                    'id.required' => 'Falta identificador de Solicitud.',
                    'id.integer' => 'Identificador de Solicitud irreconocible.',
                    'id.exists' => 'Solicitud sin coincidencia encontrada.',
                ]
            )->validate();

            $employeeRequest = EmployeeRequest::where('employee_id_affected', $validatedData['id'])
                // ->where('employee_id_authorizing', Auth::user()->id)
                ->where('status', 1)
                ->with('employeeRequestType', 'employeeRequestTypeElements')
                ->first();

            if ($employeeRequest->toArray()) {
                return response()->json($employeeRequest, 200);
            } else {
                throw new Exception('Solicitud no encontrada.');
            }
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
