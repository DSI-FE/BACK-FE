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
    // Obtener todos los clientes
    public function index(Request $request)
    {
        $rules = [
            'search' => ['nullable', 'max:250'],
            'perPage' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable'],
            'sort.order' => ['nullable', Rule::in(['id', 'name', 'lastname', 'email'])],
            'sort.key' => ['nullable', Rule::in(['asc', 'desc'])],
        ];

        $messages = [
            'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
            'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
            'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
            'sort.order.in' => 'El valor de ordenamiento es inválido.',
            'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
        ];

        $request->validate($rules, $messages);

        $search = StringsHelper::normalizarTexto($request->query('search', ''));
        $perPage = $request->query('perPage', 5);

        $sort = json_decode($request->input('sort'), true);
        $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
        $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $clientes = Cliente::with(['department', 'municipality', 'economicActivity'])
        ->where(function (Builder $query) use ($search) {
            return $query->where('cliente.nombres', 'like', '%' . $search . '%')
            ->orWhere('cliente.apellidos', 'like', '%' . $search . '%')
            ->orWhere('cliente.correoElectronico', 'like', '%' . $search . '%')
            ->orWhere('cliente.direccion', 'like', '%' . $search . '%')
            ->orWhere('cliente.telefono', 'like', '%' . $search . '%');
        })
        ->orderBy($orderBy, $orderDirection)
        ->paginate($perPage);

        // Esto es para devolver la respuesta en formato JSON con un mensaje y los datos
        $response = $clientes->toArray();
        $response['search'] = $request->query('search', '');
        $response['sort'] = [
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection
        ];

        return response()->json($response, 200);
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
