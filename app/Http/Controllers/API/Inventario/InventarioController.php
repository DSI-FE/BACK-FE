<?php

namespace App\Http\Controllers\API\Inventario;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventarios\Inventario;
use Illuminate\Support\Facades\Validator;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Helpers\StringsHelper;
use Illuminate\Database\Eloquent\Builder;

class InventarioController extends Controller
{
    //Obtener todos los productos
    public function index(Request $request)
    {

        $rules = [
            'search' => ['nullable', 'max:250'],
            'perPage' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable'],
            'sort.order' => ['nullable', Rule::in(['id, nombreProducto'])],
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
        $perPage = $request->query('perPage', 10);

        $sort = json_decode($request->input('sort'), true);
        $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
        $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

        $inventario = Inventario::with('producto', 'unidad')
        ->whereHas('producto',function (Builder $query) use ($search) {
         $query->where('nombreProducto', 'like', '%' . $search . '%');
        })
        ->orderBy($orderBy, $orderDirection)
        ->paginate($perPage);

        
        // Esto es para devolver la respuesta en formato JSON con un mensaje y los datos
        $response = $inventario->toArray();
        $response['search'] = $request->query('search', '');
        $response['sort'] = [
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection
        ];

        return response()->json($response, 200);
    }

    //Aqui se el insert al inventario y a la vez el producto
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'nombreProducto' => 'required|string|max:150',
            'equivalencia' => 'required',
            'unidadMedida' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Empezar una transacción
        DB::beginTransaction();
        try {
            // Crear o encontrar el producto
            $producto = Producto::firstOrCreate(
                ['nombreProducto' => $request->nombreProducto]
            );
    
            // Encontrar la unidad de medida
            $unidadMedida = UnidadMedida::where('id', $request->unidadMedida)->first();
    
            if (!$unidadMedida) {
                // Muestra error si la unidad de medida no existe
                return response()->json([
                    'message' => 'La unidad de medida no existe',
                ], 409); // Código de estado 409 para conflicto
            }
    
            // Verificar si ya existe un registro en inventario con el mismo producto y unidad de medida
            $inventarioExistente = Inventario::where('producto_id', $producto->id)
                ->where('unidad_medida_id', $unidadMedida->id)
                ->first();
    
            if ($inventarioExistente) {
                // Si ya existe, devolver una respuesta de error
                return response()->json([
                    'message' => 'El producto con esta unidad de medida ya existe en el inventario',
                ], 409); // Código de estado 409 para conflicto
            }
    
            // Crear el registro de inventario
            $inventario = Inventario::create([
                'producto_id' => $producto->id,
                'unidad_medida_id' => $unidadMedida->id,
                'equivalencia' => $request->equivalencia,
                'existencias' => 0,
                'precioCosto' => 0,
                'precioVenta' => 0
            ]);
    
            // Confirmar la transacción
            DB::commit();
    
            // Devolver la respuesta en formato JSON
            return response()->json([
                'message' => 'Inventario creado exitosamente',
                'data' => $inventario
            ], 201);
        } catch (\Exception $e) {
            // Deshacer la transacción en caso de error
            DB::rollBack();
    
            // Devolver una respuesta de error
            return response()->json([
                'message' => 'Error al crear el inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function show($codigo)
    {
        // Obtener inventarios por id
        $inventarios = Inventario::with(['producto', 'unidad'])
            ->where('id', $codigo)
            ->get();

        if ($inventarios->isEmpty()) {
            return response()->json([
                'message' => 'Producto no encontrado',
                'data' => []
            ], 404);
        }
        

         // Seleccionar solo los campos deseados
    $filteredInventarios = $inventarios->map(function ($inventario) {
        return [
            'id' => $inventario->id,
            'producto_id' => $inventario->producto_id,
            'nombre_producto' => $inventario->producto->nombreProducto,
            'unidad_medida' => $inventario->unidad_medida_id,
            'nombre_unidad_medida' => $inventario->unidad->nombreUnidad,
            'equivalencia' =>$inventario->equivalencia,
        ];
    });

        return response()->json([
            'message' => 'Producto con sus unidades de medida',
            'data' => $filteredInventarios
        ], 200);
    }



    //PRUEBA OBTENER POR CODIGO
    public function codigo($codigo)
    {
        // Obtener inventarios por id
        $inventarios = Inventario::with(['producto', 'unidad'])
            ->where('producto_id', $codigo)
            ->get();
    
        if ($inventarios->isEmpty()) {
            return response()->json([
                'message' => 'Producto no encontrado',
                'data' => []
            ], 404);
        }
    
        // Obtener el nombre del producto desde el primer inventario
        $nombreProducto = $inventarios->first()->producto->nombreProducto;
    
        // Estructurar la respuesta con el nombre del producto y los inventarios
        return response()->json([
            'nombreProducto' => $nombreProducto,
            'data' => $inventarios->map(function($inventario) {
                return [
                    'unidadMedida' => $inventario->unidad->nombreUnidad,
                    'equivalencia' => $inventario->equivalencia
                ];
            })
        ], 200);
    }
    



//Actualizar un campo
    public function update(Request $request, $id)
    {
        $inventario = Inventario::where('id', $id)->first();
    
        if (!$inventario) {
            return response()->json([
                'message' => 'Registro no encontrado',
            ], 404);
        }
    
        $inventario->update($request->all());

    // Actualizar el nombre del producto en la tabla productos
    if ($request->has('nombreProducto')) {
        $producto = Producto::find($inventario->id); // Asumiendo que inventario tiene producto_id

        if ($producto) {
            $producto->nombreProducto = $request->input('nombreProducto');
            $producto->save();
        }
    }
    
        return response()->json([
            'message' => 'Inventario actualizado exitosamente',
            'data' => $inventario,
        ], 200);
    }



    //obtener la suma del inventario
    public function sumaInventario()
{
    // Obtener todos los registros del inventario
    $inventories = Inventario::all();

    // Agrupar los registros por producto_id y seleccionar solo el primer registro de cada grupo
    $uniqueInventories = $inventories->groupBy('producto_id')->map(function ($group) {
        return $group->first();
    })->values();

    // Inicializar variables para almacenar los totales
    $totalExistencias = 0;
    $totalPrecioCosto = 0;
    $totalPrecioVenta = 0;

    // Recorrer los registros únicos del inventario
    foreach ($uniqueInventories as $inventory) {
        // Sumar las existencias, precios de costo y venta para el primer registro de cada producto
        $totalExistencias += $inventory->existencias;
        $totalPrecioCosto += $inventory->precioCosto * $inventory->existencias;
        $totalPrecioVenta += $inventory->precioVenta * $inventory->existencias;
    }

    // Preparar los resultados
    $result = [
        'total_existencias' => $totalExistencias,
        'total_precio_costo' => $totalPrecioCosto,
        'total_precio_venta' => $totalPrecioVenta,
    ];

    // Devolver los resultados en formato JSON
    return response()->json([
        'message' => 'Resumen de inventario',
        'data' => $result,
    ], 200);
}

public function delete($producto_id, $unidad_medida_id)
{
    // Buscar el inventario por producto_id y unidad_medida_id
    $inventario = Inventario::where('producto_id', $producto_id)
                            ->where('unidad_medida_id', $unidad_medida_id)
                            ->first();

    if (!$inventario) {
        return response()->json([
            'message' => 'Producto con la unidad de medida especificada no encontrado',
        ], 404);
    }

      // Verificar si la cantidad en inventario es mayor a 0
      if ($inventario->existencias > 0) {
        return response()->json([
            'message' => 'No se puede eliminar el producto porque aún hay existencias',
        ], 400); // Código de estado 400
    }

    // Eliminar el inventario
    $inventario->delete();

    return response()->json([
        'message' => 'Producto con la unidad de medida especificada eliminado exitosamente',
    ], 200);
}

    
}
