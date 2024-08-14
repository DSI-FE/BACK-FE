<?php

namespace App\Http\Controllers\API\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventarios\Inventario;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use App\Models\Ventas\DetalleVenta;
use Illuminate\Support\Facades\DB;

class VentasController extends Controller
{

    //Funcion para obtener todas las ventas
    public function index()
    {
        // Obtener todas las ventas
        $ventas = Venta::with('cliente')->get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de ventas',
            'data' => $ventas,
        ], 200);
    }

    //obtener una venta especifica
    public function detalleVenta($id)
    {
        // Obtener la compra con el número dado
        $venta = Venta::where('id', $id)->first();

        if (!$venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
            ], 404);
        }

        // Obtener los detalles de la compra
        $detalle = DetalleVenta::with('producto')
            ->where('venta_id', $venta->id)
            ->get();


        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Detalle de venta',
            'data' => [
                'venta' => $venta,
                'detalles' => $detalle,
            ],
        ], 200);
    }

 // Agregar una venta nueva
public function store(Request $request)
{
    // Validación de los campos de entrada
    $validator = Validator::make($request->all(), [
        'total_no_sujetas' => 'required',
        'total_exentas' => 'required',
        'total_gravadas' => 'required',
        'total_iva' => 'required',
        'total_pagar' => 'required',
        'condicion' => 'required',
        'tipo_documento' => 'required',
        'cliente_id' => 'required',
        'productos' => 'required|array',
        'productos.*.cantidad' => 'required',
        'productos.*.precio' => 'required',
        'productos.*.iva' => 'required',
        'productos.*.total' => 'required',
        'productos.*.producto_id' => 'required',
        'productos.*.unidad_medida_id' => 'required'
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
        // Crear la compra
        $venta = Venta::create([
            'total_no_sujetas' => $request->total_no_sujetas,
            'total_exentas' => $request->total_exentas,
            'total_gravadas' => $request->total_gravadas,
            'total_iva' => $request->total_iva,
            'total_pagar' => $request->total_pagar,
            'estado' => 'Pendiente',
            'condicion' => $request->condicion,
            'tipo_documento' => $request->tipo_documento,
            'cliente_id' => $request->cliente_id,
        ]);

        $detalleVentas = [];

        // Iterar sobre los productos y crear los registros de detalle de compra
        foreach ($request->productos as $producto) {
            // Obtener el inventario de la unidad de medida seleccionada
            $unidadSeleccionada = Inventario::where('producto_id', $producto['producto_id'])
                ->where('unidad_medida_id', $producto['unidad_medida_id'])
                ->first();

            if ($unidadSeleccionada) {
                // Verificar si la cantidad es mayor que las existencias
                if ($producto['cantidad'] > $unidadSeleccionada->existencias) {
                    // Revertir la transacción en caso de error
                    DB::rollback();
                    return response()->json([
                        'message' => 'Error: La cantidad solicitada excede las existencias disponibles.',
                        'producto_id' => $producto['producto_id'],
                        'unidad_medida_id' => $producto['unidad_medida_id'],
                        'existencias_disponibles' => $unidadSeleccionada->existencias,
                        'cantidad_solicitada' => $producto['cantidad']
                    ], 400);
                }

                $detalleVenta = DetalleVenta::create([
                    'cantidad' => $producto['cantidad'],
                    'precio' => $producto['precio'],
                    'iva' => $producto['iva'],
                    'total' => $producto['total'],
                    'venta_id' => $venta->id,
                    'producto_id' => $producto['id']
                ]);

                // Disminuir las existencias de la unidad de medida seleccionada
                $unidadSeleccionada->existencias -= $detalleVenta->cantidad;
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

                $detalleVentas[] = $detalleVenta;
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
            'message' => 'Venta registrada exitosamente',
            'venta' => $venta,
            'detalles' => $detalleVentas
        ], 201);
    } catch (\Exception $e) {
        // Revertir la transacción en caso de error
        DB::rollback();
        return response()->json([
            'message' => 'Error al registrar la venta',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}