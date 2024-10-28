<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\API\Ventas\VentasController;
use App\Http\Controllers\Controller;
use App\Mail\MiCorreo;
use App\Models\Clientes\Cliente;
use App\Models\DTE\Contingencia;
use Illuminate\Support\Str;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\DTE\VentasAnuladas;
use App\Models\Inventarios\Inventario;
use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use NumberToWords\NumberToWords;
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


    //Crer un DTE
    public function index(Request $request, $id, $contingenciaId)
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
        if ($venta->tipo_documento == 1) {
            $version = 1;
        } else {
            $version = 3;
        }
        DB::beginTransaction();
        try {
            // Crear el nuevo DTE
            $dte = DTE::create([
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'tipo_transmision' => $contingenciaId != 0 ? '2' : '1',
                'modelo_facturacion' => $contingenciaId != 0 ? '2' : '1',
                'codigo_generacion' => $uuid,
                'numero_control' => $numero_control,
                'id_venta' => $id,
                'ambiente' => '1',
                'version' => $version,
                'moneda' => '1',
                'tipo_documento' => $venta->tipo_documento,
                'contingencia_id' => $contingenciaId,
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
                    if ($inventario->existencias < 0) {
                        DB::rollback();
                        return response()->json([
                            'message' => 'Error: No hay suficientes existencias para el producto proporcionado.',
                            'producto_id' => $item->producto_id
                        ], 400);
                    } else {
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

            //llaamar la funcion para crear el json 
            if ($venta->tipo_documento == 3) {
                $jsonData = $this->JsonSujeto($id);
            } else {
                $jsonData = $this->obtenerJson($id);
            }
            $json = $jsonData->getData();

            // Obtener el cliente
            $cliente = Cliente::where('id', $dte->ventas->cliente_id)->first();

            // Verificar el formato del correo electrónico
            $correoElectronico = $cliente->correoElectronico;
            $correoValido = filter_var($correoElectronico, FILTER_VALIDATE_EMAIL) !== false;

            if ($correoValido) {
                // Envio del correo solo si el formato es correcto
                Mail::to($correoElectronico)->send(
                    new MiCorreo(
                        $cliente->nombres . ' ' . $cliente->apellidos,
                        $dte->fecha,
                        $dte->codigo_generacion,
                        $dte->numero_control,
                        $contenidoPDF,
                        $dte,
                        json_encode($json)
                    )
                );
            } else {
                // Si el correo es inválido, registrar un aviso sin interrumpir la transacción
                Log::warning('El correo electrónico es incorrecto: ' . $correoElectronico);
            }

            // Continúa con la transacción sin interrupción



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

    //Anular un DTE
    public function agregarInvalidacion(Request $request, $idVenta)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
           // 'tipo_invalidacion_id' => 'required',
            'motivo_invalidacion' => 'required|string|max:200',
            'responsable_id' => 'required',
            'solicitante_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }
        // Encuentra el registro DTE asociado a la venta
        $dte = DTE::with('ventas')->where('id_venta', $idVenta)->first();
        $venta = Venta::find($idVenta);

        if (!$dte) {
            return response()->json([
                'message' => 'DTE no encontrado',
            ], 404);
        }

        // Verifica si el DTE ya está anulado
        if ($dte->anulada_id) {
            return response()->json([
                'message' => 'El DTE ya ha sido anulado previamente.',
            ], 400);
        }


        // Actualiza los campos de invalidación en la tabla DTE
        $anulacion = VentasAnuladas::create([
            'tipo_invalidacion_id' => $request->tipo_invalidacion_id,
            'motivo_invalidacion' => $request->motivo_invalidacion,
            'responsable_id' => $request->responsable_id,
            'solicitante_id' => $request->solicitante_id,
            'codigo_generacion_reemplazo' => $request->codigo_generacion_reemplazo,
        ]);
        
        // Asigna el ID de la anulación recién creada al DTE
        $dte->anulada_id = $anulacion->id;
        // Guarda los cambios en DTE
        $dte->save();

        // Cambia el estado de la venta a 'Anulada'
        if ($venta) {
            $venta->estado = 'Anulada';
            $venta->save();
        }

         //Aqui vamos a implementar la conexion con Hacienda - ESTE ES EL JSON PARA ANULAR
         $jsonAnularDTE = $this->AnularFactura($idVenta);


        // Retornar una respuesta exitosa
        return response()->json([
            'message' => 'Invalidación agregada correctamente',
            'dte' => $dte,
        ], 200);
    }

    public function TransmitirContingencia($idcont)
    {

        //LLamar la funcion del json para contingencia
        $jsonData = $this->Contingencia($idcont);
        $json = $jsonData->getData();

        // Buscar la contingencia en la base de datos para actualizar el estado
        $contingencia = Contingencia::find($idcont);

        $contingencia->update([
            'estado_contingencia' => 0,
        ]);
        $contingencia->save();

        // Devolver la respuesta en formato JSON
        return response()->json([
            'message' => 'los DTEs fueron emitidos correctamente',
            'data' => $json,
        ], 200);
    }





    /******************************ESTRUCTURA DE JSON DE QUI PARA ABAJO*******************************************/







    //Json para Facturas y Creditos Fiscales
    public function obtenerJson($idVenta)
    {
        //contenido que llevara el json
        $emisor = Emisor::with(['establecimiento', 'department', 'municipality', 'economicActivity'])
            ->where('id', 1)
            ->first();
        $dte = DTE::with('tipo', 'ambiente', 'tipo', 'ventas')->where('id_venta', $idVenta)->first();
        $cliente = Cliente::with('identificacion', 'economicActivity', 'department', 'municipality')->where('id', $dte->ventas->cliente->id)->first();
        $detalle = DetalleVenta::with('producto', 'ventas')->where('venta_id', $dte->ventas->id)->get();

        //Ambiente destino
        $Codambiente = 0;
        if ($dte->ambiente == 1) {
            $Codambiente = '00';
        } else {
            $Codambiente = '01';
        }

        $ventas = $detalle->first()->ventas;
        $pago = $ventas->tipo_pago;
        //convertir el total a letras
        $numeroLetras = new NumberToWords();
        $resultado = $numeroLetras->getNumberTransformer('es');
        // Obtener el total a pagar
        $totalPagar = $ventas->total_pagar;
        // Separar parte entera y decimal
        $parteEntera = intval($totalPagar);
        $parteDecimal = ($totalPagar - $parteEntera) * 100;
        // Convertir la parte entera a letras
        $totalEnLetras = strtoupper($resultado->toWords($parteEntera)) . ' ' . sprintf('%02d', $parteDecimal) . '/100 USD';




        //receptor si es factura lleva esta estructura
        if ($dte->tipo_documento == 1) {
            $receptor = [
                'tipoDocumento' => $cliente->identificacion->codigo,
                'numeroDocumento' => $cliente->numeroDocumento,
                'nrc' => $cliente->nrc ?? null,
                'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'codActividad' => $cliente->economicActivity->codigo ?? null,
                'descActividad' => $cliente->economicActivity->actividad ?? null,
                'direccion' => [
                    'departamento' => $cliente->department->codigo,
                    'municipio' => $cliente->municipality->codigo,
                    'complemento' => $cliente->direccion
                ],
                'telefono' => $cliente->telefono ?? null,
                'correo' => $cliente->correoElectronico
            ];
        } else {
            //si es credito fiscal lleva esta estructura
            $receptor = [
                'nit' => $cliente->numeroDocumento,
                'nrc' => $cliente->nrc ?? null,
                'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'codActividad' => $cliente->economicActivity->codigo ?? null,
                'descActividad' => $cliente->economicActivity->actividad ?? null,
                'nombreComercial' => $cliente->nombres . ' ' . $cliente->apellidos ?? null,
                'direccion' => [
                    'departamento' => $cliente->department->codigo,
                    'municipio' => $cliente->municipality->codigo,
                    'complemento' => $cliente->direccion
                ],
                'telefono' => $cliente->telefono ?? null,
                'correo' => $cliente->correoElectronico
            ];
        }


        $totalCantidad = 0;
        //FOREACH PARA RECORRER TODOS LOS PRODUCTOS DE LA VENTA
        $conta = 1;
        $cuerpoDocumento = [];
        foreach ($detalle as $det) {

            //iva item
            if ($det->producto->producto->combustible) {
                $ivaItem = ((($det->total) - $det->cantidad * 0.30) / 1.13) * 0.13;
            }

            $tributos = null;
            $tributosDetallados = [];
            $totalCantidad += $det->cantidad;

            // Si es combustible y tipo_documento es CCF (2)
            if ($det->producto->producto->combustible && $dte->tipo_documento == 2) {
                $tributos = [
                    '20', // IVA
                    'D1', // Fovial
                    'C8', // Cotrans
                ];
                $tributosDetallados = [
                    [
                        'codigo' => '20',
                        'descripcion' => 'Impuesto al Valor Agregado 13%',
                        'valor' => ((($ventas->total_pagar) - $totalCantidad * 0.30) / 1.13) * 0.13, // IVA sobre el total del item
                    ],
                    [
                        'codigo' => 'D1',
                        'descripcion' => 'FOVIAL ($0.20 Ctvs. por galón)',
                        'valor' => $totalCantidad * 0.20, // FOVIAL por cantidad de galones
                    ],
                    [
                        'codigo' => 'C8',
                        'descripcion' => 'COTRANS ($0.10 Ctvs. por galón)',
                        'valor' => $totalCantidad * 0.10, // COTRANS por cantidad de galones
                    ],
                ];
            }

            // Si es combustible y tipo_documento es factura (1)
            elseif ($det->producto->producto->combustible && $dte->tipo_documento == 1) {
                $tributos = [
                    'D1', // Fovial
                    'C8', // Cotrans
                ];
                $tributosDetallados = [
                    [
                        'codigo' => 'D1',
                        'descripcion' => 'FOVIAL ($0.20 Ctvs. por galón)',
                        'valor' => $totalCantidad * 0.20,
                    ],
                    [
                        'codigo' => 'C8',
                        'descripcion' => 'COTRANS ($0.10 Ctvs. por galón)',
                        'valor' => $totalCantidad * 0.10,
                    ],
                ];
            }

            // Si NO es combustible y tipo_documento es CCF (2), solo aplica IVA
            elseif (!$det->producto->producto->combustible && $dte->tipo_documento == 2) {
                $tributos = [
                    '20', // IVA
                ];
                $tributosDetallados = [
                    [
                        'codigo' => '20',
                        'descripcion' => 'Impuesto al Valor Agregado 13%',
                        'valor' => number_format(($det->total / 1.13) * 0.13, 4), // IVA sobre el total del item
                    ],
                ];
            }

            // Si NO es combustible y tipo_documento es factura (1), no hay tributos
            elseif (!$det->producto->producto->combustible && $dte->tipo_documento == 1) {
                $tributos = null;
                $tributosDetallados = [];
            }

            if ($dte->tipo_documento == 1) {
                $ivaItem = [
                    'ivaItem' => $det->producto->producto->combustible ? number_format((($det->total - $det->cantidad * 0.3) / 1.13) * 0.13, 4) : number_format(($det->total / 1.13) * 0.13, 4),

                ];
            } else {
                $ivaItem = [];
            }




            if ($det->producto->producto->combustible) {
                $precioNetoUni  = $dte->tipo_documento == 2 ? number_format(($det->precio - 0.3) / 1.13, 4) : number_format($det->precio - 0.30, 4);
                $precioNetoTot  = $dte->tipo_documento == 2 ? number_format(($det->total - $det->cantidad * 0.3) / 1.13, 4) : number_format($det->total - $det->cantidad * 0.3, 4);
                $totalGravadas = $dte->tipo_documento == 2 ? number_format(($ventas->total_pagar - $totalCantidad * 0.3) / 1.13, 4) : number_format(($ventas->total_pagar - $totalCantidad * 0.30), 4);
            } else {
                $precioNetoUni = $dte->tipo_documento == 2 ? number_format($det->precio / 1.13, 4) : number_format($det->precio, 4);
                $precioNetoTot = $dte->tipo_documento == 2 ? number_format($det->total / 1.13, 4) : number_format($det->total, 4);
                $totalGravadas = $dte->tipo_documento == 2 ? number_format($ventas->total_pagar / 1.13, 4) : number_format($ventas->total_pagar, 4);
            }

            // Crear el cuerpo del documento para el producto
            $cuerpoDocumento[] = [
                'numItem' => $conta++,
                'tipoItem' => $det->producto->producto->tipo_producto_id,
                'numeroDocumento' => null,
                'cantidad' => $det->cantidad,
                'codigo' => $det->producto->producto_id,
                'codTributo' => null,
                'uniMedida' => $det->producto->unidad->codigo,
                'descripcion' => $det->producto->nombreProducto,
                'precioUni' => $precioNetoUni, // $dte->tipo_documento == 2 ? number_format($det->precio / 1.13, 4) : number_format($det->precio, 4),
                'montoDescu' => 0,
                'ventaNoSuj' => 0,
                'ventaExenta' => 0,
                'ventaGravada' =>  $precioNetoTot, //$dte->tipo_documento == 2 ? number_format($det->total / 1.13, 4) : number_format($det->total, 4),
                'tributos' => $tributos,
                'psv' => 0,
                'noGravado' => 0,
            ];

            // Si el tipo de documento es 1, añadir el campo ivaItem
            if ($dte->tipo_documento == 1) {
                $cuerpoItem['ivaItem'] = number_format(($det->total / 1.13) * 0.13, 4);
            }
        }

        // Inicialización del resumen sin totalIva
        $resumen = [
            'totalNoSuj' => 0,
            'totalExenta' => 0,
            'totalGravada' => $totalGravadas,
            'subTotalVentas' => $totalGravadas,
            'descuNoSuj' => 0,
            'descuExenta' => 0,
            'descuGravada' => 0,
            'porcentajeDescuento' => 0,
            'totalDescu' => 0,
            'tributos' => $tributosDetallados,
            'subTotal' => $totalGravadas,
            'ivaRete1' => 0,
            'reteRenta' => 0,
            'montoTotalOperacion' => $ventas->total_pagar,
            'totalNoGravado' => 0,
            'totalPagar' => $ventas->total_pagar,
            'totalLetras' => $totalEnLetras,
            'saldoFavor' => 0,
            'condicionOperacion' => $ventas->condicion,
            'pagos' => [
                [
                    'codigo' => $pago->codigo,
                    'montoPago' => ($ventas->condicion == 2) ? 0 : $ventas->total_pagar,
                    'referencia' => null,
                    'plazo' => ($ventas->condicion == 2) ? '01' : null,
                    'periodo' => ($ventas->condicion == 2) ? '15' : null,
                ]
            ],
            'numPagoElectronico' => ''
        ];

        // Calcular y agregar totalIva solo si el tipo de documento no es 2
        if ($dte->tipo_documento == 1) {
            $resumen['totalIva'] = $det->producto->producto->combustible
                ? number_format((($ventas->total_pagar - $totalCantidad * 0.30) / 1.13) * 0.13, 4)
                : number_format(($ventas->total_pagar / 1.13) * 0.13, 4);
        }

        // Estructura completa del JSON
        $jsonData = [
            [
                'identificacion' => [
                    'version' => $dte->version,
                    'ambiente' => $Codambiente,
                    'tipoDte' => $dte->tipo->codigo,
                    'numeroControl' => $dte->numero_control,
                    'codigoGeneracion' => $dte->codigo_generacion,
                    'tipoModelo' => $dte->modelo_facturacion,
                    'tipoOperacion' => $dte->tipo_transmision,
                    'tipoContingencia' => $dte->tipo_contingencia ?? null,
                    'motivoContin' => $dte->motivo_contingencia ?? null,
                    'fecEmi' => $dte->fecha,
                    'horEmi' => $dte->hora,
                    'tipoMoneda' => 'USD',
                ],
                'documentoRelacionado' => null,
                'emisor' => [
                    'nit' => str_replace('-', '', $emisor->nit),
                    'nrc' => str_replace('-', '', $emisor->nrc),
                    'nombre' => $emisor->nombre,
                    'codActividad' => $emisor->economicActivity->codigo,
                    'descActividad' => $emisor->economicActivity->actividad,
                    'nombreComercial' => $emisor->nombre_comercial ?? null,
                    'tipoEstablecimiento' => $emisor->establecimiento->codigo,
                    'direccion' => [
                        'departamento' => $emisor->department->codigo,
                        'municipio' => $emisor->municipality->codigo,
                        'complemento' => $emisor->direccion
                    ],
                    'telefono' => str_replace('-', '', $emisor->telefono),
                    'correo' => $emisor->correo,
                    'codEstableMH' => null,
                    'codEstable' => null,
                    'codPuntoVentaMH' => null,
                    'codPuntoVenta' => null,
                ],
                'receptor' => $receptor,
                'otrosDocumentos' => null,
                'ventaTercero' => null,
                'cuerpoDocumento' => $cuerpoDocumento,
                'resumen' => $resumen,  // Aquí se incluye el resumen calculado
                'extension' => null,
                'apendice' => [
                    'campo' => 'Datos del documento',
                    'etiqueta' => 'Sello',
                    'valor' => $dte->sello_recepcion ?? '',
                ],
            ]
        ];
        return response()->json([$jsonData]);
    }


    //Json para sujeto excluido
    public function JsonSujeto($idVenta)
    {
        //contenido que llevara el json
        $emisor = Emisor::with(['establecimiento', 'department', 'municipality', 'economicActivity'])
            ->where('id', 1)
            ->first();
        $dte = DTE::with('tipo', 'ambiente', 'tipo', 'ventas')->where('id_venta', $idVenta)->first();
        $cliente = Cliente::with('identificacion', 'economicActivity', 'department', 'municipality')->where('id', $dte->ventas->cliente->id)->first();
        $detalle = DetalleVenta::with('producto', 'ventas')->where('venta_id', $dte->ventas->id)->get();

        //Ambiente destino
        $Codambiente = 0;
        if ($dte->ambiente == 1) {
            $Codambiente = '00';
        } else {
            $Codambiente = '01';
        }

        $ventas = $detalle->first()->ventas;
        $pago = $ventas->tipo_pago;
        //convertir el total a letras
        $numeroLetras = new NumberToWords();
        $resultado = $numeroLetras->getNumberTransformer('es');
        // Obtener el total a pagar
        $totalPagar = $ventas->total_pagar;
        // Separar parte entera y decimal
        $parteEntera = intval($totalPagar);
        $parteDecimal = ($totalPagar - $parteEntera) * 100;
        // Convertir la parte entera a letras
        $totalEnLetras = strtoupper($resultado->toWords($parteEntera)) . ' ' . sprintf('%02d', $parteDecimal) . '/100 USD';
        $version = 1;

        //Recorrer el detalle de la venta
        $conta = 1;
        $cuerpoDocumento = [];
        foreach ($detalle as $det) {
            // Crear el cuerpo del documento para el producto
            $cuerpoDocumento[] = [
                'numItem' => $conta++,
                'tipoItem' => $det->producto->producto->tipo_producto_id,
                'cantidad' => $det->cantidad,
                'codigo' => $det->producto->producto_id,
                'uniMedida' => $det->producto->unidad->codigo,
                'descripcion' => $det->producto->nombreProducto,
                'precioUni' => $det->precio,
                'montoDescu' => 0,
                'compra' =>  $det->total,

            ];
        }

        $jsonData = [
            'identificacion' => [
                'version' => $version,
                'ambiente' => $Codambiente,
                'tipoDte' => $dte->tipo->codigo,
                'numeroControl' => $dte->numero_control,
                'codigoGeneracion' => $dte->codigo_generacion,
                'tipoModelo' => $dte->modelo_facturacion,
                'tipoOperacion' => $dte->tipo_transmision,
                'tipoContingencia' => $dte->tipo_contingencia ?? null,
                'motivoContin' => $dte->motivo_contingencia ?? null,
                'fecEmi' => $dte->fecha,
                'horEmi' => $dte->hora,
                'tipoMoneda' => 'USD',
            ],
            'documentoRelacionado' => null,
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nrc' => str_replace('-', '', $emisor->nrc),
                'nombre' => $emisor->nombre,
                'codActividad' => $emisor->economicActivity->codigo,
                'descActividad' => $emisor->economicActivity->actividad,
                'nombreComercial' => $emisor->nombre_comercial ?? null,
                'tipoEstablecimiento' => $emisor->establecimiento->codigo,
                'direccion' => [
                    'departamento' => $emisor->department->codigo,
                    'municipio' => $emisor->municipality->codigo,
                    'complemento' => $emisor->direccion
                ],
                'telefono' => str_replace('-', '', $emisor->telefono),
                'correo' => $emisor->correo,
                'codEstableMH' => null,
                'codEstable' => null,
                'codPuntoVentaMH' => null,
                'codPuntoVenta' => null,
                'sujetoExcluido' => [
                    'tipoDocumento' => $cliente->identificacion->codigo,
                    'numDocumento' => $cliente->numeroDocumento,
                    'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                    'codActividad' => $cliente->economicActivity->codigo ?? null,
                    'descActividad' =>  $cliente->economicActivity->actividad ?? null,
                    'direccion' => [
                        'departamento' => $cliente->department->codigo,
                        'municipio' => $cliente->municipality->codigo,
                        'complemento' => $cliente->direccion
                    ],
                    'telefono' => $cliente->telefono ?? null,
                    'correo' => $cliente->correoElectronico
                ],
                'cuerpoDocumento' => $cuerpoDocumento,
                'resumen' => [
                    'totalCompra' => $ventas->total_pagar,
                    'descu' => 0,
                    'totalDescu' => 0,
                    'subTotal' => $ventas->total_pagar,
                    'ivaRete1' => 0,
                    'reteRenta' => 0,
                    'totalPagar' => $ventas->total_pagar,
                    'totalLetras' => $totalEnLetras,
                    'condicionOperacion' => $ventas->condicion,
                    'pagos' => null,
                    'observaciones' => ""
                ],
                "apendice" => [
                    [
                        "campo" => "Datos del documento",
                        "etiqueta" => "Sello",
                        "valor" => $dte->sello_recepcion ?? '',
                    ]
                ]
            ],
        ];

        return response()->json([$jsonData]);
    }

    //Json para anular factura
    public function AnularFactura($idVenta)
    {

        //contenido que llevara el json
        $emisor = Emisor::with(['establecimiento', 'department', 'municipality', 'economicActivity'])
            ->where('id', 1)
            ->first();
        $dte = DTE::with('tipo', 'ambiente', 'tipo', 'ventas', 'anulada')->where('id_venta', $idVenta)->first();
        $cliente = Cliente::with('identificacion', 'economicActivity', 'department', 'municipality')->where('id', $dte->ventas->cliente->id)->first();

        //Ambiente destino
        $Codambiente = 0;
        if ($dte->ambiente == 1) {
            $Codambiente = '00';
        } else {
            $Codambiente = '01';
        }

        $jsonData = [
            'identificacion' => [
                'version' => $dte->version,
                'ambiente' => $Codambiente,
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()), // se crea uno nuevo
                'fechaAnula' => now()->format('Y-m-d'),
                'horaAnula' => now()->format('H:i:s')
            ],
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nombre' => $emisor->nombre,
                'tipoEstablecimiento' => $emisor->establecimiento->codigo,
                'nombreEstablecimiento' => $emisor->establecimiento->valores,
                'codEstableMH' => null,
                'codEstable' => null,
                'codPuntoVentaMH' => null,
                'codPuntoVenta' => null,
                'telefono' => $emisor->telefono,
                'correo' => $emisor->correo,
            ],
            'documento' => [
                'tipoDTE' => $dte->tipo->codigo,
                'codigoGeneracion' => $dte->codigo_generacion,
                'selloRecibido' => $dte->sello_recepcion ?? '',
                'numeroControl' => $dte->numero_control,
                'fechaEmision' => $dte->fecha,
                'monto' => $dte->ventas->tipo_documento == 1 || $dte->ventas->tipo_documento == 2 ? $dte->ventas->total_pagar : 0,
                'codigoGeneracionR' => $dte->anulada->codigo_generacion_reemplazo ?? null,
                'tipoDocumento' => $cliente->identificacion->codigo,
                'numDocumento' => $cliente->numeroDocumento,
                'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'telefono' => $cliente->telefono,
                'correo' => $cliente->correoElectronico
            ],
            'motivo' => [
                'tipoAnulacion' => $dte->anulada->tipo_invalidacion_id,
                'motivoAnulacion' => $dte->anulada->motivo_invalidacion,
                'nombreResponsable' => $dte->anulada->responsableAnular->nombre,
                'docResponsable' => $dte->anulada->responsableAnular->tipoDocumento->codigo,
                'numResponsable' => $dte->anulada->responsableAnular->numero_documento,
                'nombreSolicita' => $dte->anulada->solicitanteAnular->nombres . ' ' . $dte->anulada->solicitanteAnular->apellidos,
                'tipDocSolicita' => $dte->anulada->solicitanteAnular->identificacion->codigo,
                'numDocSolicita' => $dte->anulada->solicitanteAnular->numeroDocumento,
            ],
            'sello' => 'DLFDM',
        ];
        return response()->json([$jsonData]);
    }

    //Json para contingencia
    public function Contingencia($idConti)
    {

        //contenido que llevara el json
        $emisor = Emisor::with(['establecimiento', 'department', 'municipality', 'economicActivity'])
            ->where('id', 1)
            ->first();
        $dte = DTE::with('tipo', 'ambiente', 'ventas', 'anulada')->where('contingencia_id', $idConti)->get();

        $primerDTE = $dte->first();
        //Ambiente destino
        $Codambiente = 0;
        if ($primerDTE->ambiente == 1) {
            $Codambiente = '00';
        } else {
            $Codambiente = '01';
        }

        $contador = 1;
        foreach ($dte as $det) {
            $detalleDTE[] = [
                'noItem' => $contador++,
                'tipoDoc' => $det->tipo->codigo,
                'codigoGeneracion' => $det->codigo_generacion,
            ];
        }



        $jsonData = [
            'identificacion' => [
                'version' => $primerDTE->version,
                'ambiente' => $Codambiente,
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()), // se crea uno nuevo,
                'fTransmision' => now()->format('Y-m-d'),
                'hTransmision' => now()->format('H:i:s')
            ],
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nombre' => $emisor->nombre,
                'nombreResponsable' => '',
                'tipoDocResponsable' => '',
                'numeroDocResponsable' => '',
                'tipoEstablecimiento' => $emisor->establecimiento->codigo,
                'codEstableMH' => null,
                'codPuntoVenta' => null,
                'telefono' => $emisor->telefono,
                'correo' => $emisor->correo,
            ],
            'detalleDTE' => $detalleDTE,
            'motivo' => [
                'fInicio' => $primerDTE->contingencia->fechaInicio ?? null,
                'fFin' => $primerDTE->contingencia->fechaFin ?? null,
                'hInicio' => $primerDTE->contingencia->horaInicio ?? null,
                'hFin' => $primerDTE->contingencia->horaFin ?? null,
                'tipoContingencia' => $primerDTE->contingencia->tipoContingencia->id ?? null,
                'motivoContingencia' => $primerDTE->contingencia->motivo_contingencia ?? null,
            ],
            'sello' => 'DLFDM',
        ];

        return response()->json([$jsonData]);
    }
}
