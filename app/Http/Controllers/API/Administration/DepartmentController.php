<?php

namespace App\Http\Controllers\API\Administration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Administration\Department;
use App\Models\Administration\Municipality;
use Exception;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::orderBy('name', 'asc')->get();

        return response()->json($departments, 200);
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
                ['id' => ['required','integer','exists:adm_departments,id']],
                [
                    'id.required' => 'Falta identificador de Departamento.',
                    'id.integer' => 'Identificador de Departamento irreconocible.',
                    'id.exists' => 'Departamento solicitado sin coincidencia.',
                ]
            )->validate();

            $department = Department::findOrFail($validatedData['id']);

            $department->load(['municipalities' => function ($query) {
                $query->orderBy('name', 'asc');
            }]);

            return response()->json($department, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $admDepartment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $admDepartment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $admDepartment)
    {
        //
    } 

}
