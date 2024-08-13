<?php

namespace App\Http\Controllers\API\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Compra;
use App\Models\Compras\DetalleCompra;
use App\Models\Inventarios\Inventario;
use Illuminate\Support\Facades\Validator;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Illuminate\Support\Facades\DB;

class ComprasController extends Controller
{

    //Funcion para obtener todas las compras
    public function index()
    {
        // Obtener todos los productos
        $compras = Compra::with('proveedor')->get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de compras',
            'data' => $compras,
        ], 200);
    }

    //Funcion para actualizar los datos generales de las compras
    public function update(Request $request, $id)
    {
        $compra = Compra::where('id', $id)->first();

        if (!$compra) {
            return response()->json([
                'message' => 'No existe la compra',
            ], 404);
        }

        $compra->update($request->all());

        return response()->json([
            'message' => 'Compra actualizada exitosamente',
            'data' => $compra,
        ], 200);
    }


    public function detalleCompra($numero)
    {
        // Obtener la compra con el número dado
        $compra = Compra::where('numeroCCF', $numero)->first();

        if (!$compra) {
            return response()->json([
                'message' => 'Compra no encontrada',
            ], 404);
        }

        // Obtener los detalles de la compra
        $detalle = DetalleCompra::with('producto', 'unidad')
            ->where('compra_id', $compra->id)
            ->get();


        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Detalle de compra',
            'data' => [
                'compra' => $compra,
                'detalles' => $detalle,
            ],
        ], 200);
    }


//Agregar compra
public function store(Request $request)
{
    // Validación de los campos de entrada
    $validator = Validator::make($request->all(), [
        'fecha' => 'required',
        'numeroCCF' => 'required',
        'ivaCompra' => 'required',
        'totalCompra' => 'required',
        'proveedor_id' => 'required',
        'comprasExentas' => 'required',
        'comprasGravadas' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Errores de validación',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Verificar si ya existe una compra con el mismo numeroCCF
    $compraExistente = Compra::where('numeroCCF', $request->numeroCCF)->first();
    if ($compraExistente) {
        return response()->json([
            'message' => 'La compra con este numeroCCF ya ha sido registrada',
            'compra' => $compraExistente
        ], 409); // 409 Conflict
    }

    // Empezar una transacción
    DB::beginTransaction();
    try {
        // Crear la compra
        $compra = Compra::create([
            'fecha' => $request->fecha,
            'numeroCCF' => $request->numeroCCF,
            'comprasExentas' => $request->comprasExentas,
            'comprasGravadas' => $request->comprasGravadas,
            'ivaCompra' => $request->ivaCompra,
            'ivaPercibido' => $request->ivaPercibido,
            'totalCompra' => $request->totalCompra,
            'proveedor_id' => $request->proveedor_id
        ]);

        $detallesCompra = [];

        // Iterar sobre los productos y crear los registros de detalle de compra
        foreach ($request->productos as $producto) {
            $detalleCompra = DetalleCompra::create([
                'compra_id' => $compra->id,
                'producto_id' => $producto['producto_id'],
                'unidad_medida_id' => $producto['unidad_medida_id'],
                'costo' => $producto['costo'],
                'ivaCompra' => $producto['iva'],
                'total' => $producto['total'],
                'cantidad' => $producto['cantidad']
            ]);

            // Obtener el inventario de la unidad de medida seleccionada
            $unidadSeleccionada = Inventario::where('producto_id', $producto['producto_id'])
                ->where('unidad_medida_id', $producto['unidad_medida_id'])
                ->first();

            if ($unidadSeleccionada) {
                // Aumentar las existencias de la unidad de medida seleccionada
                $unidadSeleccionada->existencias += $detalleCompra->cantidad;
                $unidadSeleccionada->save();

                // Actualizar las existencias de otras unidades de medida del mismo producto
                $unidadesProducto = Inventario::where('producto_id', $producto['producto_id'])->get();
                foreach ($unidadesProducto as $unidad) {
                    if ($unidad->unidad_medida_id != $unidadSeleccionada->unidad_medida_id) {
                        if ($unidadSeleccionada->equivalencia > 1) {
                            $unidad->existencias = $unidadSeleccionada->existencias / $unidadSeleccionada->equivalencia * $unidad->equivalencia;
                        } else {
                            $unidad->existencias = $unidadSeleccionada->existencias * $unidad->equivalencia;
                        }
                        $unidad->save();
                    }
                }

                $detallesCompra[] = $detalleCompra;
            } else {
                // Revertir la transacción en caso de error
                DB::rollback();
                return response()->json([
                    'message' => 'Error: No se encontró el inventario para el producto y unidad de medida proporcionados.',
                    'producto_id' => $producto['producto_id'],
                    'unidad_medida_id' => $producto['unidad_medida_id']
                ], 400);
            }
        }

        // Confirmar la transacción
        DB::commit();

        return response()->json([
            'message' => 'Compra registrada exitosamente',
            'compra' => $compra,
            'detalles' => $detallesCompra
        ], 201);
    } catch (\Exception $e) {
        // Revertir la transacción en caso de error
        DB::rollback();
        return response()->json([
            'message' => 'Error al registrar la compra',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    
}
