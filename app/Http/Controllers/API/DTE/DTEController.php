<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\API\Ventas\VentasController;
use App\Http\Controllers\Controller;
use App\Mail\MiCorreo;
use App\Models\Clientes\Cliente;
use Illuminate\Support\Str;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Inventarios\Inventario;
use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class DTEController extends Controller
{
    protected $ventasController;

    public function __construct(VentasController $ventasController)
    {
        $this->ventasController = $ventasController;
    }
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
            'detalle' => $detalle,
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
            'detalle' => $detalle,
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
        if ($venta->estado == "Finalizada") {
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

        //crear la version del json, si es factura es 1 si es credito fiscal es 3
        if($venta->tipo_documento ==1){
            $version =1;
        }else{
            $version =3;
        }
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
                    if($inventario->existencias < 0){
                        DB::rollback();
                        return response()->json([
                            'message' => 'Error: No hay suficientes existencias para el producto proporcionado.',
                            'producto_id' => $item->producto_id
                        ], 400);

                    }else{
                        $inventario->save();
                    }
                    

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

            //Llamar el metodo para crear la factura
            $factura = $this->ventasController->descargarFactura($id);
            $contenidoPDF = $factura->getContent();
            //contenido que llevara el json
            $emisor = Emisor::where('id', 1)->first();
            $cliente = Cliente::where('id', $venta->cliente_id)->first();
            
            $jsonData = [
                [
                    'identificacion' => [
                        'version' => $version,
                        'ambiente' => $dte->ambiente,
                        'tipoDte' => $dte->tipo_documento,
                        'numeroControl' => $dte->numero_control,
                        'codigoGeneracion' => $dte->codigo_generacion,
                        'tipoModelo' => $dte->modelo_facturacion,
                        'tipoOperacion' => $dte->tipo_transmision,
                        'tipoContingencia' => $dte->tipo_contingencia || null,
                        'motivoContin' => $dte->motivo_contingencia || null,
                        'fecEmi' => $dte->fecha,
                        'horEmi' => $dte->hora,
                        'tipoMoneda' => 'USD'
                    ],
                    'documentoRelacionado' => null,
                    'emisor' => [
                        'nit' => $emisor->nit,
                        'nrc' => $emisor->nrc,
                        'nombre' => $emisor->nombre,
                        'codActividad' => $emisor->actividad_economica,
                        'descActividad' => $emisor->actividad_economica,
                        'nombreComercial' => $emisor->nombre_comercial || null,
                        'tipoEstablecimiento' => '02',
                        'direccion' => [
                            'departamento' => $emisor->departamento_id,
                            'municipio' => $emisor->municipio_id,
                            'complemento' => $emisor->direccion
                        ],
                        'telefono' => $emisor->telefono,
                        'codEstableMH' => null,
                        'codEstable' => null,
                        'codPuntoVentaMH' => null,
                        'codPuntoVenta' => null,
                        'correo' => $emisor->correo
                    ],
                    'receptor' => [
                        'tipoDocumento' =>$cliente->numeroDocumento,
                        'nrc' => $cliente->nrc || null,
                        'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                        'codActividad' => $cliente->economic_activity_id || null,
                        'descActividad' => $cliente->economic_activity_id || null,
                        'direccion' => [
                            'departamento' => $cliente->department_id,
                            'municipio' => $cliente->municipality_id,
                            'complemento' => $cliente->direccion
                        ],
                        'telefono' => $cliente->telefono || null,
                        'correo' => $cliente->correoElectronico
                    ],
                    'otrosDocumentos' => null,
                    'ventaTercero' => null,
                    'cuerpoDocumento' => [
                        [
                            'numItem' => 1,
                            'tipoItem' => 1,
                            'numeroDocumento' => null,
                            'cantidad' => $detalle[0]->cantidad,
                            'codigo' => $detalle[0]->producto->producto_id,
                            'codTributo' => null,
                            'uniMedida' => $detalle[0]->producto->unidad_medida_id,
                            'descripcion' => $detalle[0]->producto->producto_id,
                            'precioUni' => $detalle[0]->total_gravadas,
                            'montoDescu' => 0,
                            'ventaNoSuj' => 0,
                            'ventaExenta' => $detalle[0]->total_exentas,
                            'ventaGravada' => $detalle[0]->total_pagar,
                            'tributos' => null,
                            'psv' => 0,
                            'noGravado' => 0,
                            'ivaItem' => $detalle[0]->total_iva,
                        ]
                    ],
                    'resumen' => [
                        'totalNoSuj' => 0,
                        'totalExenta' => 0,
                        'totalGravada' => 3,
                        'subTotalVentas' => 3,
                        'descuNoSuj' => 0,
                        'descuExenta' => 0,
                        'descuGravada' => 0,
                        'porcentajeDescuento' => 0,
                        'totalDescu' => 0,
                        'tributos' => [],
                        'subTotal' => 3,
                        'ivaRete1' => 0,
                        'reteRenta' => 0,
                        'montoTotalOperacion' => 3,
                        'totalNoGravado' => 0,
                        'totalPagar' => 3,
                        'totalLetras' => 'TRES 00 /100',
                        'totalIva' => 0.34,
                        'saldoFavor' => 0,
                        'condicionOperacion' => 1,
                        'pagos' => [
                            [
                                'codigo' => '01',
                                'montoPago' => 3,
                                'referencia' => null,
                                'plazo' => null,
                                'periodo' => null
                            ]
                        ],
                        'numPagoElectronico' => ''
                    ],
                    'extension' => null,
                    'apendice' => null
                ]
            ];
            //Envio del correo
            $cliente = Cliente::where('id', $dte->ventas->cliente_id)->first();
            Mail::to($cliente->correoElectronico)->send(
                new MiCorreo(
                    $cliente->nombres . ' ' . $cliente->apellidos,
                    $dte->fecha,
                    $dte->codigo_generacion,
                    $dte->numero_control,
                    $contenidoPDF,
                    $dte,
                    json_encode($jsonData)
                )
            );


            DB::commit();

            return response()->json([
                'message' => 'DTE creado exitosamente',
                'data' => $dte,
                'detalle' => $detalle
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
