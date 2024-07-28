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
use Illuminate\Database\Eloquent\Builder;

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

     //Obtener todos los proveedores con búsqueda, ordenamiento y paginación
    public function index(Request $request)
    {
        $rules = [
            'search' => ['nullable', 'max:250'],
            'perPage' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable'],
            'sort.order' => ['nullable', Rule::in(['id', 'nombre', 'nit', 'codigo', 'nrc'])],
            'sort.key' => ['nullable', Rule::in(['asc', 'desc'])],  
        ];

        $messages = [
            'search.max' => 'El criterio de busqueda enviado excede la cantidad maxima permitida.',
            'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
            'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
            'sort.order.in' => 'El valor de ordenamiento es inválido.',
            'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
        ];

        $request->validate($rules, $messages);

        $search = $request->input('search', '');
        $perPage = $request->input('perPage', 5);

        $sort = json_decode($request->input('sort'), true);
        $orderBy = isset($sort['key']) && !empty($sort['order']) ? $sort['key'] : 'id';
        $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

        //Esto es para obtener todos los proveedores junto con sus relaciones utilizando Eloquent ORM
        $proveedores = Proveedor::with('tipoProveedor')
        ->where(function (Builder $query) use ($search) {
            return $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('nit', 'like', '%' . $search . '%')
                ->orWhere('codigo', 'like', '%' . $search . '%')
                ->orWhere('nrc', 'like', '%' . $search . '%')
                ->orWhere('serie', 'like', '%' . $search . '%');
                
        })
        ->orderBy($orderBy, $orderDirection)
        ->paginate($perPage);

        // Esto es para devolver la respuesta en formato JSON con un mensaje y los datos
        $response = $proveedores->toArray();
        $response['search'] = $request->query('search', '');
        $response['sort'] = [
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection,
        ];

        return response()->json($response, 200);
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
