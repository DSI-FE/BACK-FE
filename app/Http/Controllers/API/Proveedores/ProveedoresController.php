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
    //POST de cliente
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:50',
            'nrc' => 'nullable|string|max:50',
            'nombre' => 'required|string|max:100',
            'nit' => 'required|string|max:50',
            'serie' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Crear un nuevo registro de proveedor
        $Proveedores = Proveedor::create($request->all());

        return response()->json([
            'message' => 'Proveedor creado exitosamente',
            'data' => $Proveedores,
        ], 201);
    }

    //Obtener todos los proveedores
    public function index()
    {
        // Esto es para obtener todos los proveedores utilizando Eloquent ORM
        $proveedores = Proveedor::all();

        // Esto es para devolver la respuesta en formato JSON con un msj y los datos
        return response()->json([
            'message' => 'Listado de todos los proveedores',
            'data' => $proveedores,
        ], 200);
    }

    public function show($id)
    {
        $proveedores = Proveedor::find($id);

        if (!$proveedores) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Detalles de este proveedor',
            'data' => $proveedores,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $proveedores = Proveedor::find($id);

        if (!$proveedores) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $proveedores->update($request->all());

        return response()->json([
            'message' => 'Proveedor actualizado exitosamente',
            'data' => $proveedores,
        ], 200);
    }

    public function delete($id)
    {
        $proveedores = Proveedor::find($id);

        if (!$proveedores) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $proveedores->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente',
        ], 200);
    }
}
