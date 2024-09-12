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
        $ventas = Venta::with('cliente', 'condicion', 'tipo_documento')->get();

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
        $venta = Venta::with('condicion', 'tipo_documento')->where('id', $id)->first();

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
                'venta' => [$venta],
                'detalles' => $detalle,
            ],
        ], 200);
    }

    // Agregar una venta nueva
    public function store(Request $request)
    {
        // Validación de los campos de entrada
        $validator = Validator::make($request->all(), [
            'fecha' => 'required',
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
            // Crear la venta
            $venta = Venta::create([
                'fecha' =>$request->fecha,
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
                    $detalleVenta = DetalleVenta::create([
                        'cantidad' => $producto['cantidad'],
                        'precio' => $producto['precio'],
                        'iva' => $producto['iva'],
                        'total' => $producto['total'],
                        'venta_id' => $venta->id,
                        'producto_id' => $producto['producto_id']
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

    //actualizar una venta
    public function update(Request $request, $id)
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
            'productos.*.unidad_medida_id' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        DB::beginTransaction();
        try {
            // Obtener la venta a actualizar
            $venta = Venta::find($id);
            if (!$venta) {
                return response()->json([
                    'message' => 'La venta no fue encontrada'
                ], 404);
            }

            if($venta->estado == "Finalizada"){
                return response()->json([
                    'message' => 'Esta venta no se puede modificar porque ya fue facturada'
                ], 404);
            }
    
            // Actualizar los datos de la venta
            $venta->update([
                'total_no_sujetas' => $request->input('total_no_sujetas', 0),
                'total_exentas' => $request->input('total_exentas', 0),
                'total_gravadas' => $request->input('total_gravadas', 0),
                'total_iva' => $request->input('total_iva', 0),
                'total_pagar' => $request->input('total_pagar', 0),
                'condicion' => $request->condicion,
                'tipo_documento' => $request->tipo_documento,
                'cliente_id' => $request->cliente_id,
            ]);
    
            // Eliminar los detalles de venta existentes
            DetalleVenta::where('venta_id', $venta->id)->delete();
    
            $detalleVentas = [];
            foreach ($request->productos as $producto) {
                // Buscar el ID de inventario basado en producto_id y unidad_medida_id
                $inventario = Inventario::where('id', $producto['producto_id'])
                    ->where('unidad_medida_id', $producto['unidad_medida_id'])
                    ->first();
    
                if (!$inventario) {
                    // Manejar el caso en que no se encuentra el inventario
                    DB::rollback();
                    return response()->json([
                        'message' => 'Inventario no encontrado para el producto y unidad de medida proporcionados',
                    ], 404);
                }
    
                // Crear el detalle de venta
                $detalleVenta = DetalleVenta::create([
                    'cantidad' => $producto['cantidad'],
                    'precio' => $producto['precio'],
                    'iva' => $producto['iva'],
                    'total' => $producto['total'],
                    'venta_id' => $venta->id,
                    'producto_id' => $inventario->id 
                ]);
    
                $detalleVentas[] = $detalleVenta;
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json([
                'message' => 'Venta actualizada exitosamente',
                'venta' => $venta,
                'detalles' => $detalleVentas
            ], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

//Funcion para eliminar una venta
    public function delete($id)
{
    DB::beginTransaction();
    try {
        // Obtener la venta a eliminar
        $venta = Venta::find($id);
        if (!$venta) {
            return response()->json([
                'message' => 'La venta no fue encontrada'
            ], 404);
        }

        // Eliminar los detalles de venta asociados
        DetalleVenta::where('venta_id', $venta->id)->delete();

        if($venta->estado == "Finalizada"){
            return response()->json([
                'message' => 'Esta venta no se puede eliminar porque ya fue facturada'
            ], 404);
        }else{
            // Eliminar la venta
        $venta->delete();
        }
        

        // Confirmar la transacción
        DB::commit();

        return response()->json([
            'message' => 'Venta eliminada exitosamente'
        ], 200);
    } catch (\Exception $e) {
        // Revertir la transacción en caso de error
        DB::rollback();
        return response()->json([
            'message' => 'Error al eliminar la venta',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    
}
