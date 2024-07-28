<?php

namespace App\Http\Controllers\API\Clientes;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Clientes\Cliente;
use Illuminate\Support\Facades\Auth;
use App\Models\Clientes\ClientesModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class ClientesController extends Controller
{
    // Obtener todos los clientes
    public function index()
    {
        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $clientes = Cliente::with(['department', 'municipality', 'economicActivity'])->get();

        // Esto es para devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Listado de todos los clientes',
            'data' => $clientes,
        ], 200);
    }

    public function show($id)
    {
        $clientes = Cliente::with(['department', 'municipality', 'economicActivity'])->find($id);

        if (!$clientes) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Detalles de este cliente',
            'data' => $clientes,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $clientes = Cliente::find($id);

        if (!$clientes) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $clientes->update($request->all());

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'data' => $clientes,
        ], 200);
    }

    public function delete($id)
    {
        $clientes = Cliente::find($id);

        if (!$clientes) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $clientes->delete();

        return response()->json([
            'message' => 'Cliente eliminado exitosamente',
        ], 200);
    }

    // Crear un nuevo cliente
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validatedData = $request->validate([
            'codigo' => 'required|string|max:50',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'numeroDocumento' => 'required|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'nrc' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'correoElectronico' => 'nullable|string|max:100',
            'department_id' => 'required|exists:adm_departments,id',
            'municipality_id' => 'required|exists:adm_municipalities,id',
            'economic_activity_id' => 'required|exists:actividad_economica,id',
        ]);

        // Crear el nuevo cliente
        $cliente = Cliente::create($validatedData);

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'data' => $cliente,
        ], 201);
    }
}
