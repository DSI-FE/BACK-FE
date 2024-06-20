<?php

namespace App\Http\Controllers\API\Proveedores;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Proveedores\Proveedor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProveedoresController extends Controller
{
    //POST de proveedor
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:50',
            'nrc' => 'nullable|string|max:50',
            'nombre' => 'required|string|max:100',
            'nit' => 'required|string|max:50',
            'serie' => 'nullable|string|max:50',
            'tipo_proveedor_id' => 'required|exists:tipo_proveedor,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Crear un nuevo registro de proveedor
        $proveedor = Proveedor::create($request->all());

        return response()->json([
            'message' => 'Proveedor creado exitosamente',
            'data' => $proveedor,
        ], 201);
    }

    //Obtener todos los proveedores
    public function index()
    {
        // Obtener todos los proveedores con la relación tipoProveedor
        $proveedores = Proveedor::with('tipoProveedor')->get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Listado de todos los proveedores',
            'data' => $proveedores,
        ], 200);
    }

    // Mostrar un proveedor específico
    public function show($id)
    {
        $proveedor = Proveedor::with('tipoProveedor')->find($id);

        if (!$proveedor) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Detalles del proveedor',
            'data' => $proveedor,
        ], 200);
    }

    // Actualizar un proveedor
    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => 'sometimes|required|string|max:50',
            'nrc' => 'nullable|string|max:50',
            'nombre' => 'sometimes|required|string|max:100',
            'nit' => 'sometimes|required|string|max:50',
            'serie' => 'nullable|string|max:50',
            'tipo_proveedor_id' => 'sometimes|required|exists:tipo_proveedor,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $proveedor->update($request->all());

        return response()->json([
            'message' => 'Proveedor actualizado exitosamente',
            'data' => $proveedor,
        ], 200);
    }

    // Eliminar un proveedor
    public function delete($id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $proveedor->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente',
        ], 200);
    }
}
