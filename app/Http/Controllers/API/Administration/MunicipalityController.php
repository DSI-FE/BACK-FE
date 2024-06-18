<?php

namespace App\Http\Controllers\API\Administration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Administration\Municipality;
use App\Models\Administration\Department;

class MunicipalityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $municipalities = Municipality::all();

        return response()->json($municipalities, 200);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_municipalities,id']],
                [
                    'id.required' => 'Falta identificador de Municipio.',
                    'id.integer' => 'Identificador de Municipio irreconocible.',
                    'id.exists' => 'Municipio solicitado sin coincidencia.',
                ]
            )->validate();

            $municipality = Municipality::find($validatedData['id']);
            $municipality->department;

            return response()->json($municipality, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Municipality $admMunicipality)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Municipality $admMunicipality)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Municipality $admMunicipality)
    {
        //
    }

}
