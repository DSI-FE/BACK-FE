<?php

namespace App\Http\Controllers\API\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\MiCorreo;
use App\Models\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventarios\Inventario;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Productos\UnidadMedida;
use App\Models\Ventas\DetalleVenta;
//use BaconQrCode\Encoder\QrCode;
use Illuminate\Support\Facades\DB;
use Exception;
use File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use NumberToWords\NumberToWords;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Storage;
use TCPDF;
use TCPDF_COLORS;

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
        // Obtener la venta con el número dado
        $venta = Venta::with('condicion', 'tipo_documento')->where('id', $id)->first();

        if (!$venta) {
            return response()->json([
                'message' => 'Venta no encontrada',
            ], 404);
        }

        // Obtener los detalles de la venta
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
                'fecha' => $request->fecha,
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

            // Iterar sobre los productos y crear los registros de detalle de venta
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
                        'producto_id' => $unidadSeleccionada['id']
                    ]);

                    /*     // Disminuir las existencias de la unidad de medida seleccionada
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
                    }*/

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
                'id' => $venta->id,
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

            if ($venta->estado == "Finalizada") {
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
                $inventario = Inventario::where('producto_id', $producto['producto_id'])
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
                    'producto_id' => $inventario['id']
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
                    'message' => 'La venta no fue encontrada, no se puede facturar'
                ], 404);
            }

            // Eliminar los detalles de venta asociados
            DetalleVenta::where('venta_id', $venta->id)->delete();

            if ($venta->estado == "Finalizada") {
                return response()->json([
                    'message' => 'Esta venta no se puede eliminar porque ya fue facturada'
                ], 404);
            } else {
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

    public static function descargarFactura($id)
    {

        // Obtener el DTE junto con su venta asociada
        $dte = DTE::with('ventas',  'ambiente', 'moneda', 'tipo')->where('id_venta', $id)->first();

        // Obtener los detalles de la venta
        $detalle = DetalleVenta::with('producto')
            ->where('venta_id', $id)
            ->get();
        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $emisor = Emisor::with(['department', 'municipality', 'economicActivity'])
            ->where('id', 1)->first();

        //convertir el total a letras
        $numeroLetras = new NumberToWords();
        $resultado = $numeroLetras->getNumberTransformer('es');
        // Obtener el total a pagar
        $totalPagar = $dte->ventas->total_pagar;
        // Separar parte entera y decimal
        $parteEntera = intval($totalPagar);
        $parteDecimal = ($totalPagar - $parteEntera) * 100;
        // Convertir la parte entera a letras
        $totalEnLetras = strtoupper($resultado->toWords($parteEntera)) . ' ' . sprintf('%02d', $parteDecimal) . '/100 DOLARES';

        $ambientes = [
            1 => (object)['codigo' => '00', 'nombre' => 'Modo prueba'],
            2 => (object)['codigo' => '01', 'nombre' => 'Modo producción']
        ];
        $codigoAmbiente = isset($ambientes[$dte->ambiente]) ? $ambientes[$dte->ambiente]->codigo : null;
        //URL que va a contener el pdf
        $url = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $codigoAmbiente . '&codGen=' . $dte->codigo_generacion . '&fechaEmi=' . $dte->fecha;

        //Diseño del pdf
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $pdf->writeHTML('<h3 style="text-align: center; font-size: 13px; font-family: \'Times New Roman\', Times, serif;">DOCUMENTO TRIBUTARIO ELECTRONICO</h3>');
        $pdf->writeHTML('<h3 style="text-align: center; font-size: 13px; font-family: \'Times New Roman\', Times, serif;">' . $dte->tipo->nombre . '</h3>');
        // Generar el código QR en el centro
        $pdf->write2DBarcode($url, 'QRCODE,H', 92, 25, 25, 25, array('border' => false), 'N');

        // Definir el contenido de la tabla
        $tablaDTE = '
<table border="0" cellspacing="5" cellpadding="5" width="100%; ">
    <tr>
        <td style="text-align: left; width: 55%; font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
            <p>Código de generación: <br>' . $dte->codigo_generacion . '</p>
            <p>Número de control: <br>' . $dte->numero_control . '</p>
            <p>Sello de recepción: <br>' . $dte->sello_recepcion . '</p>
        </td>
        <td style="text-align: left; width: 55%; font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
            <p>Modelo de facturación: <br>Modelo facturación previo</p>
            <p>Tipo de transmisión: <br>Transmisión normal</p>
            <p>Fecha y hora de generación: <br>' . $dte->fecha . ' ' . $dte->hora . '</p>
        </td>
    </tr>
</table>';

        // Escribir la tabla en el PDF
        $pdf->writeHTML($tablaDTE, true, false, true, false, '');


        $tablaEmisor = '
<table style="font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
    <tr>
        <th style="text-align: center; border: 1px solid gray;  height: 25px; background-color: #73E1B7; font-size: 12px;"><strong>Emisor</strong></th>
    </tr>
    <tr>
       <td style="font-family: \'Times New Roman\', Times, serif; font-weight: bold; font-size: 14px;">' . $emisor->nombre . '</td>

    </tr>
    <tr>
       <td>NIT: ' . $emisor->nit . '</td>
    </tr>
    <tr>
       <td>NRC: ' . $emisor->nrc . '</td>
    </tr>
    <tr>
       <td>Actividad económica: ' . $emisor->economic_activity_name . '</td>
    </tr>
    <tr>
       <td>Dirección: ' . $emisor->direccion . ', ' . $emisor->municipality->name . ', ' . $emisor->department->name . '</td>
    </tr>
    <tr>
       <td>Teléfono: ' . $emisor->telefono . '</td>
    </tr>
    <tr>
       <td>Correo electrónico: ' . $emisor->correo . '</td>
    </tr>
    <tr>
       <td>Tipo de establecimiento: CASA MATRIZ</td>
    </tr>
</table>';


        $tablaCliente = '
<table style=" font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
    <tr>
         <th style="text-align: center; border: 1px solid gray;  height: 25px; background-color: #73E1B7;  font-size: 12px;"><strong>Receptor</strong></th>
    </tr>
    <tr>
         <th style="font-family: \'Times New Roman\', Times, serif; font-weight: bold; font-size: 14px;">' . $dte->ventas->cliente_nombre . '</th>
    </tr>
    <tr>
         <th>Tipo de Documento: DUI</th>
    </tr>
    <tr>
         <th>Numero de Documento: ' . $dte->ventas->cliente->numeroDocumento . '</th>
    </tr>
    <tr>
         <th>NRC: ' . $dte->ventas->cliente->nrc . '</th>
    </tr>
    <tr>
         <th>Actividad económica: ' . $dte->ventas->cliente->economic_activity_name . '</th>
    </tr>
    <tr>
         <th>Dirección: ' . $dte->ventas->cliente->direccion . ', ' . $dte->ventas->cliente->municipality_name . ', ' . $dte->ventas->cliente->department_name . '</th>
    </tr>
    <tr>
         <th>Correo electrónico: ' . $dte->ventas->cliente->correoElectronico . '</th>
    </tr>
    <tr>
         <th>Teléfono: ' . $dte->ventas->cliente->telefono . '</th>
    </tr>
</table>';

        $tablaContenido = ' <br><br><br>
<table style="border-collapse: collapse; width: 100%;  font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
    <tr style="background-color: #23DEA1; text-align: center; font-weight: bold">
         <th style="width: 23px;">N°</th>
         <th style="width: 46px;">Cantidad</th>
         <th style="width: 200px;">Descripción</th>
         <th style="width: 42px;">Unidad</th>
         <th style="width: 42px;">Precio Unitario</th>
         <th style="width: 42px;">Desc Item</th>
         <th style="width: 47px;">Ventas no sujetas</th>
         <th style="width: 42px;">Ventas Exentas</th>
         <th style="width: 50px;">Ventas Gravadas</th>
    </tr>';
        // Iteramos sobre el array para agregar los productos a la tabla
        $numero = 1;

        // Iterar solo sobre los productos
        foreach ($detalle as $item) {
            if ($dte->tipo_documento == 2) {
                $tablaContenido .= '
            <tr style="font-size: 9px; ">
                 <td style="height: 15px">' . $numero++ . '</td>
                 <td>' . $item['cantidad'] . '</td>
                 <td>' . $item['producto']['nombre_producto'] . '</td>
                 <td>' . $item['producto']['unidad_medida'] . '</td>
                 <td>$' . number_format($item['precio'] / 1.13, 2) . '</td>
                 <td>$0.00</td>
                 <td>$0.00</td>
                 <td>$0.00</td>
                 <td>$' . number_format($item['total'] / 1.13, 2) . '</td>
            </tr>';
            } else {
                $tablaContenido .= '
            <tr style="font-size: 9px">
                 <td style="height: 15px">' . $numero++ . '</td>
                 <td>' . $item['cantidad'] . '</td>
                 <td>' . $item['producto']['nombre_producto'] . '</td>
                 <td>' . $item['producto']['unidad_medida'] . '</td>
                 <td>$' . number_format($item['precio'], 2) . '</td>
                 <td>$0.00</td>
                 <td>$0.00</td>
                 <td>$0.00</td>
                 <td>$' . number_format($item['total'], 2) . '</td>
            </tr>';
            }
        }

        // Colocar la suma de ventas después del foreach
        if ($dte->tipo_documento == 2) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . $dte->ventas->total_gravadas . '</td>
            </tr>';
        } else {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . $dte->ventas->total_pagar . '</td>
            </tr>';
        }

        // Añadir el resto del contenido
        $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Monto global Desc., Rebajas y otros a ventas no sujetas:</td>
            <td colspan="1">$ 0.00</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: right;">Monto global Desc., Rebajas y otros a ventas Exentas:</td>
            <td colspan="1">$ 0.00</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: right;">Monto global Desc., Rebajas y otros a ventas Gravadas:</td>
            <td colspan="1">$ 0.00</td>
        </tr>';

        if ($dte->tipo_documento == 2) {
            $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Subtotal:</td>
            <td colspan="1">$ ' . $dte->ventas->total_gravadas . '</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: right;">IVA:</td>
            <td colspan="1">$ ' . $dte->ventas->total_iva . '</td>
        </tr>';
        } else {
            $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Subtotal:</td>
            <td colspan="1">$ ' . $dte->ventas->total_pagar . '</td>
        </tr>';
        }

        $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">IVA Retenido:</td>
            <td colspan="1">$ 0.00</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: right;">Retención de renta:</td>
            <td colspan="1">$ 0.00</td>
        </tr>
        <tr>
            <td colspan="8" style="text-align: right;">Monto total de la operación:</td>
            <td colspan="1">$ 0.00</td>
        </tr><br>
        <tr>
            <td colspan="8" style="text-align: right;">Total a pagar:</td>
            <td colspan="1"><strong>$ ' . $dte->ventas->total_pagar . '</strong></td>
        </tr><hr>
        <tr>
            <td colspan="9" style="text-align: left; background-color: #23DEA1; height: 25px;">VALOR EN LETRAS:  ' . $totalEnLetras . '</td>
        </tr>';

        $tablaContenido .= '
</table>';


        // Escribir la tabla del Emisor (a la izquierda)
        $pdf->writeHTMLCell(90, '', 10, '', $tablaEmisor, 0, 0, 0, true, 'L', true);

        // Escribir la tabla del Cliente (a la derecha)
        $pdf->writeHTMLCell(90, '', 110, '', $tablaCliente, 0, 1, 0, true, 'L', true);

        //tabla de productos
        $pdf->writeHTMLCell('', '', '', '', $tablaContenido, 0, 1, 0, true, 'L', true);

        //JSON que se le enviará al correo
        $jsonData = ([
            'data' => $dte,
            'detalle' => $detalle
        ]);
        set_time_limit(120); // Establecer el tiempo de ejecución a 120 segundos



        return response($pdf->Output('Factura_' . $dte->codigo_generacion . '.pdf', 'S'))
        ->header('Content-Type', 'application/pdf');
    }
}
