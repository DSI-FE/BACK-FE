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
use App\Helpers\StringsHelper;
use Illuminate\Database\Eloquent\Builder;


class ClientesController extends Controller
{


    public function index(Request $request)
    {
        try {

            $perPage = (int) $request->input('perPage', 10);
            $currentPage = (int) $request->input('current_page', 1);
            $search = $request->input('search', '');

            $query = Cliente::with(['department', 'municipality', 'economicActivity', 'identificacion'])
                ->orderBy('id', 'ASC'); 

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombres', 'LIKE', '%' . $search . '%')
                        ->orWhere('apellidos', 'LIKE', '%' . $search . '%')
                        ->orWhere('correoElectronico', 'LIKE', '%' . $search . '%')
                        ->orWhere('direccion', 'LIKE', '%' . $search . '%')
                        ->orWhere('telefono', 'LIKE', '%' . $search . '%');
                });
            }

            $result = $query->paginate($perPage, ['*'], 'page', $currentPage);

            return response()->json([
                'data' => $result->items(),
                'total' => $result->total(),
                'per_page' => $result->perPage(),
                'current_page' => $result->currentPage(),
                'total_pages' => $result->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocurrió un error al obtener los clientes: ' . $e->getMessage()], 400);
        }
    }


    public function show($id)
    {
        $clientes = Cliente::with(['department', 'municipality', 'economicActivity', 'identificacion'])->find($id);

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
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            // 'tipoIdentificacion' => 'required|max:50',
            // 'numeroDocumento' => 'required|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'nrc' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'correoElectronico' => 'nullable|string|max:100',
            //'department_id' => 'required|exists:adm_departments,id',
            //'municipality_id' => 'required|exists:adm_municipalities,id',
            // 'economic_activity_id' => 'required|exists:actividad_economica,id',
        ]);

        // Crear el nuevo cliente
        $cliente = Cliente::create($validatedData);

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'data' => $cliente,
        ], 201);
    }
}
