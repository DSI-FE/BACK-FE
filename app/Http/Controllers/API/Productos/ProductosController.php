<?php

namespace App\Http\Controllers\API\Productos;

use App\Http\Controllers\Controller;
use App\Models\Clientes\Cliente;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\DTE\Responsable;
use App\Models\Inventarios\Inventario;
use App\Models\Productos\UnidadMedida;
use App\Models\Ventas\DetalleVenta;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Luecano\NumeroALetras\NumeroALetras;
use NumberToWords\NumberToWords;
use Illuminate\Support\Str;

class ProductosController extends Controller
{
    public function index()
    {
        // Obtener todos los productos agrupados por su nombre
        $productosAgrupados = Inventario::all()
            ->groupBy('producto_id')
            ->map(function ($items) {
                // Obtener el nombre del producto y las unidades de medida asociadas
                $producto = $items->first()->producto;
                $unidades = $items->map(function ($item) {
                    return [
                        'producto_id' => $item->id,
                        'id' => $item->unidad->id,
                        'nombreUnidad' => $item->unidad->nombreUnidad,
                        'existencias' => $item->existencias,
                        'precioVenta' => $item->precioVenta,
                        'combustible' => $item->producto->combustible ?? '',
                    ];
                });

                return [
                    'id' => $producto->id,
                    'nombreProducto' => $producto->nombreProducto,
                    'unidades' => $unidades
                ];
            })
            ->values();

        // Devolver la respuesta en formato JSON con un mensaje y los datos agrupados
        return response()->json([
            'message' => 'Listado de todos los productos',
            'data' => $productosAgrupados,
        ], 200);
    }


    //Obtener todos las unidades de medida
    public function show()
    {
        // Obtener todos los productos
        $unidades = UnidadMedida::all();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Lista de todas las unidades de medida',
            'data' => $unidades,
        ], 200);
    }

    public function prueba2($idVenta)
    {

        $json = $this->prueba($idVenta);
        return response()->json($json);
    }

    public function prueba($idVenta)
    {
        //contenido que llevara el json
        $emisor = Emisor::with(['establecimiento', 'department', 'municipality', 'economicActivity'])
            ->where('id', 1)
            ->first();
        $dte = DTE::with('tipo', 'ambiente', 'tipo', 'ventas', 'contingencia')->where('id_venta', $idVenta)->first();
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
                        'valor' => number_format(($ventas->total_pagar / 1.13) * 0.13, 4), // IVA sobre el total del item
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

        //version 
        $version = 3;
        // Calcular y agregar totalIva solo si el tipo de documento no es 2
        if ($dte->tipo_documento == 1) {
            $resumen['totalIva'] = $det->producto->producto->combustible
                ? number_format((($ventas->total_pagar - $totalCantidad * 0.30) / 1.13) * 0.13, 4)
                : number_format(($ventas->total_pagar / 1.13) * 0.13, 4);

            $version = 1;
        }

        // Estructura completa del JSON
        $jsonData = [
            [
                'identificacion' => [
                    'version' => $version,
                    'ambiente' => $Codambiente,
                    'tipoDte' => $dte->tipo->codigo,
                    'numeroControl' => $dte->numero_control,
                    'codigoGeneracion' => $dte->codigo_generacion,
                    'tipoModelo' => $dte->modelo_facturacion,
                    'tipoOperacion' => $dte->tipo_transmision,
                    'tipoContingencia' => $dte->contingencia->tipoContingencia->id ?? null,
                    'motivoContin' => $dte->contingencia->motivo_contingencia ?? null,
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
    public function prueba3($idVenta)
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
                'tipoContingencia' => $dte->contingencia->tipoContingencia->id ?? null,
                'motivoContin' => $dte->contingencia->motivo_contingencia ?? null,
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

    public function AnularFactura($idVenta) {

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
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()),// se crea uno nuevo
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

    public function Contingencia($idConti){

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
            $detalleDTE [] = [
                'noItem' => $contador++,
                'tipoDoc' => $det->tipo->codigo,
                'codigoGeneracion' => $det->codigo_generacion,
            ];
        }



        $jsonData = [
            'identificacion' => [
                'version' => $primerDTE->version,
                'ambiente' => $Codambiente,
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()),// se crea uno nuevo,
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

    //prueba segunda base
    public function second(){
        $clientes = DB::connection('mysql_secondary')->table('cliente')->get();
        $clientesbase1 = Cliente::all();

        return response()->json([
            'base 2' =>$clientes,
            'base 1' =>$clientesbase1
        ]);
    }

}
