<?php

namespace App\Http\Controllers\API\Proveedores;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Proveedores\TipoProveedor;
use Illuminate\Support\Facades\Validator;

class TipoProveedorController extends Controller
{
    // Crear un nuevo tipo de proveedor
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Crear un nuevo registro de tipo de proveedor
        $tipoProveedor = TipoProveedor::create($request->all());

        return response()->json([
            'message' => 'Tipo de proveedor creado exitosamente',
            'data' => $tipoProveedor,
        ], 201);
    }

    // Obtener todos los tipos de proveedores
    public function index()
    {
        $tiposProveedores = TipoProveedor::all();

        return response()->json([
            'message' => 'Listado de todos los tipos de proveedores',
            'data' => $tiposProveedores,
        ], 200);
    }

    // Obtener un tipo de proveedor por su ID
    public function show($id)
    {
        $tipoProveedor = TipoProveedor::find($id);

        if (!$tipoProveedor) {
            return response()->json([
                'message' => 'Tipo de proveedor no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Detalles del tipo de proveedor',
            'data' => $tipoProveedor,
        ], 200);
    }

    // Actualizar un tipo de proveedor
    public function update(Request $request, $id)
    {
        $tipoProveedor = TipoProveedor::find($id);

        if (!$tipoProveedor) {
            return response()->json([
                'message' => 'Tipo de proveedor no encontrado',
            ], 404);
        }

        $tipoProveedor->update($request->all());

        return response()->json([
            'message' => 'Tipo de proveedor actualizado exitosamente',
            'data' => $tipoProveedor,
        ], 200);
    }

    // Eliminar un tipo de proveedor
    public function delete($id)
    {
        $tipoProveedor = TipoProveedor::find($id);

        if (!$tipoProveedor) {
            return response()->json([
                'message' => 'Tipo de proveedor no encontrado',
            ], 404);
        }

        $tipoProveedor->delete();

        return response()->json([
            'message' => 'Tipo de proveedor eliminado exitosamente',
        ], 200);
    }
}
