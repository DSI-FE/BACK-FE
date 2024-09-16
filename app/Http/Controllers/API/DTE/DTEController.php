<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Inventarios\Inventario;
use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DTEController extends Controller
{
    // Obtener un DTE específico por ID de dte
    public function verDte($id)
    {
        // Obtener el DTE junto con su venta asociada
        $dte = DTE::with('ventas',  'ambiente', 'moneda', 'tipo')->where('id', $id)->first();

        // Verificar si el DTE existe
        if (!$dte) {
            return response()->json([
                'message' => 'DTE no encontrado',
            ], 404);
        }

        // Obtener los detalles de la venta
        $detalle = DetalleVenta::with('producto')
            ->where('venta_id', $dte->id_venta)
            ->get();

        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $emisor = Emisor::with(['department', 'municipality', 'economicActivity'])
            ->where('id', 1)->first();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Detalles del DTE',
            'data' => $dte,
            'emisor' => $emisor,
            'detalle' => $detalle
        ], 200);
    }

    // Obtener un DTE específico por ID de venta
    public function verVentaDTE($id)
    {
        // Obtener el DTE junto con su venta asociada
        $dte = DTE::with('ventas',  'ambiente', 'moneda', 'tipo')->where('id_venta', $id)->first();

        // Verificar si el DTE existe
        if (!$dte) {
            return response()->json([
                'message' => 'DTE no encontrado',
            ], 404);
        }

        // Obtener los detalles de la venta
        $detalle = DetalleVenta::with('producto')
            ->where('venta_id', $id)
            ->get();

        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $emisor = Emisor::with(['department', 'municipality', 'economicActivity'])
            ->where('id', 1)->first();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Detalles del DTE',
            'data' => $dte,
            'emisor' => $emisor,
            'detalle' => $detalle
        ], 200);
    }


    public function index(Request $request, $id)
    {
        // Ver cual venta quiere facturar el usuario
        $venta = Venta::find($id);
        if (!$venta) {
            return response()->json([
                'message' => 'La venta no fue encontrada'
            ], 404);
        }
        if($venta->estado == "Finalizada"){
            return response()->json([
                'message' => 'La venta ya fue facturada'
            ], 404);
        }
    
        // Generar código UUID y número de control
        $uuid = strtoupper(Str::uuid()->toString());
        $ultimoRegistro = DTE::orderBy('id', 'desc')->first();
        $ultimoNumControl = $ultimoRegistro ? $ultimoRegistro->numero_control : 'DTE-01-M001P001-000000000000000';
        $UltimosDigitos = substr($ultimoNumControl, -15);
        $nuevoCodigo = str_pad(strval(intval($UltimosDigitos) + 1), 15, '0', STR_PAD_LEFT);
        $numero_control = 'DTE-' . '0' . $venta->tipo_documento . '-M001P001-' . $nuevoCodigo;
    
        DB::beginTransaction();
        try {
            // Crear el nuevo DTE
            $dte = DTE::create([
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'tipo_transmision' => '1',
                'modelo_facturacion' => '1',
                'codigo_generacion' => $uuid,
                'numero_control' => $numero_control,
                'id_venta' => $id,
                'ambiente' => '1',
                'version' => '1',
                'moneda' => '1',
                'tipo_documento' => $venta->tipo_documento
            ]);
    
            // Actualizar estado de la venta
            $venta->update(['estado' => 'Finalizada']);
    
            // Obtener detalles de la venta
            $detalle = DetalleVenta::where('venta_id', $venta->id)->get();
    
            foreach ($detalle as $item) {
                // Buscar el inventario directamente por el id (que en detalle_venta es el producto_id)
                $inventario = Inventario::find(id: $item->producto_id);
    
                if ($inventario) {
                    // Disminuir existencias
                    $inventario->existencias -= $item->cantidad;
                    $inventario->save();
    
                    // Actualizar existencias en otras unidades equivalentes si es necesario
                    $unidadesProducto = Inventario::where('producto_id', $inventario->producto_id)->get();
                    foreach ($unidadesProducto as $unidad) {
                        if ($unidad->id != $inventario->id) {
                            if ($inventario->equivalencia > 1) {
                                $unidad->existencias = $inventario->existencias / $inventario->equivalencia * $unidad->equivalencia;
                            } else {
                                $unidad->existencias = $inventario->existencias * $unidad->equivalencia;
                            }
                            $unidad->save();
                        }
                    }
                } else {
                    // Si no se encuentra inventario, revertir transacción
                    DB::rollback();
                    return response()->json([
                        'message' => 'Error: No se encontró el inventario para el producto proporcionado.',
                        'producto_id' => $item->producto_id
                    ], 400);
                }
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'DTE creado exitosamente',
                'data' => $dte,
                'detalle' =>$detalle
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al realizar la facturacion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
