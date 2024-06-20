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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class ClientesController extends Controller
{
    //POST de cliente
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:50',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'numeroDocumento' => 'required|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'nrc' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'correoElectronico' => 'nullable|string|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Crear un nuevo registro de cliente
        $clientes = Cliente::create($request->all());

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'data' => $clientes,
        ], 201);
    }

    //Obtener todos los clientes
    public function index()
    {
        // Esto es para obtener todos los clientes utilizando Eloquent ORM
        $clientes = Cliente::all();

        // Esto es para devolver la respuesta en formato JSON con un msj y los datos
        return response()->json([
            'message' => 'Listado de todos los clientes',
            'data' => $clientes,
        ], 200);
    }

    public function show($id)
    {
        $clientes = Cliente::find($id);

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
}
