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

        //Validacion de los campos de entrada
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

        // Empezar una transaccion
        DB::beginTransaction();
        try {
            // Crear o encontrar la compra
            $compra = Compra::firstOrCreate([
                'fecha' => $request->fecha,
                'numeroCCF' => $request->numeroCCF,
                'comprasExentas' => $request->comprasExentas,
                'comprasGravadas' => $request->comprasGravadas,
                'ivaCompra' => $request->ivaCompra,
                'ivaPercibido' => $request->ivaPercibido,
                'totalCompra' => $request->totalCompra,
                'proveedor_id' => $request->proveedor_id
            ]);

            $producto = Inventario::where(['producto_id' => $request->producto_id])->first();
            $unidadMedida = UnidadMedida::where(['id' => $request->unidad_medida_id])->first();


            // Crear el registro de detalle de compra
            $detalleCompra =  DetalleCompra::create([
                'compra_id' => $compra->id,
                'producto_id' => $producto->id,
                'unidad_medida_id' => $unidadMedida->id,
                'costo' => $request->costo,
                'iva' => $request->iva,
                'total' => $request->total,
                'cantidad' => $request->cantidad
            ]);

            //Incrementar los productos en existencias
            $productoAct = Inventario::where(['producto_id' => $request->producto_id])->get();
            // Iterar sobre cada producto y actualizar las existencias
            foreach ($productoAct as $prod) {
                $prod->existencias += ($detalleCompra->cantidad * $prod->equivalencia);
                $prod->save();
            }
            


            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Compra registrada exitosamente',
                'compra' => $detalleCompra
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
