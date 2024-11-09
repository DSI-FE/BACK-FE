<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\API\Ventas\VentasController;
use App\Http\Controllers\Controller;
use App\Mail\MiCorreo;
use App\Models\Clientes\Cliente;
use App\Models\DTE\Contingencia;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\DTE\VentasAnuladas;
use App\Models\Inventarios\Inventario;
use App\Models\MH\DteAuth;
use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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


    //Crear un DTE
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

        // Determinar el código del tipo de documento
        if ($venta->tipo_documento == 1) {
            $codigoC = '01';
        } elseif ($venta->tipo_documento == 2) {
            $codigoC = '03';
        } elseif ($venta->tipo_documento == 3) {
            $codigoC = '14';
        } else {
            $codigoC = '00'; // Código por defecto si el tipo de documento no coincide
        }

        // Generar código UUID
        $uuid = strtoupper(Str::uuid()->toString());

        // Obtener el último número de control, sin importar el tipo de documento
        // $ultimoRegistro = DTE::orderBy('id', 'desc')->first();
        //  $ultimoNumControl = $ultimoRegistro ? $ultimoRegistro->numero_control : 'DTE-01-M001P001-000000000000000';
        // Extraer los últimos 15 dígitos y convertir a entero
        //  $UltimosDigitos = (int) substr($ultimoNumControl, -15);
        // Incrementar el número y asegurarse de que tenga 15 dígitos
        // $nuevoCodigo = str_pad($UltimosDigitos + 1, 15, '0', STR_PAD_LEFT);
        // Formar el nuevo número de control, incluyendo el código del tipo de documento
        //$numero_control = 'DTE-' . $codigoC . '-M001P001-' . $nuevoCodigo;

        $emisor = Emisor::find(1);

        //PRUEBA PARA EL NUMERO DE CONTROL
        $numerosFinales = $emisor->ultimo_num_control;
        $numeroFormateado = str_pad($numerosFinales, 15, '0', STR_PAD_LEFT);
        $numero = 'DTE-' . $codigoC . '-M001P001-' . $numeroFormateado;
        $numeroIncrementado = $numerosFinales + 1;
        $emisor->ultimo_num_control = $numeroIncrementado;



        //crear la version del json, si es factura es 1 si es credito fiscal es 3
        if ($venta->tipo_documento == 1 || $venta->tipo_documento == 3) {
            $version = 1;
        } else {
            $version = 3;
        }
        DB::beginTransaction();
        try {

            //SI SE CREA EL DTE SE INCREMENTA EL NUMERO DE CONTROL
            $emisor->save();

            // Crear el nuevo DTE
            $dte = DTE::create([
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'tipo_transmision' => $contingenciaId != 0 ? 2 : 1,
                'modelo_facturacion' => $contingenciaId != 0 ? 2 : 1,
                'codigo_generacion' => $uuid,
                'numero_control' => $numero,
                'id_venta' => $id,
                'ambiente' => '1',
                'version' => $version,
                'moneda' => '1',
                'tipo_documento' => $venta->tipo_documento,
                'contingencia_id' => $contingenciaId,
            ]);

            //Guarda el QR en la carpeta y la ruta en la base
            if ($dte->ambiente == 1) {
                $codigoAmbiente = '00';
            } else {
                $codigoAmbiente = '01';
            }
            $url = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $codigoAmbiente . '&codGen=' . $dte->codigo_generacion . '&fechaEmi=' . $dte->fecha;

            $qr = new QrCode($url);
            $writer = new PngWriter();

            //Ruta donde se guardara el QR
            $fileName = $dte->codigo_generacion . '.png';
            $path = storage_path('app/public/QRCODES/' . $fileName);

            //Escribe el QR
            $result = $writer->write($qr);
            $result->saveToFile($path);

            //Guarda la ruta en la base
            $dte->qr_code = $fileName;
            $dte->save();

            // Actualizar estado de la venta
            $venta->update(['estado' => 'Finalizada']);

            // Obtener detalles de la venta
            $detalle = DetalleVenta::where('venta_id', $venta->id)->get();

            foreach ($detalle as $item) {
                // Buscar el inventario directamente por el id (que en detalle_venta es el producto_id)
                $inventario = Inventario::find(id: $item->producto_id);

                if ($inventario) {
                    // Disminuir existencias
                    // $inventario->existencias -= $item->cantidad;
                    // if ($inventario) {
                    // Si el tipo de producto no es 2, se valida la existencia
                    if ($inventario->producto->tipo_producto_id != 2) {
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
                    } else {
                        // Si el tipo de producto es 2, solo guardar sin validar existencias
                        // $inventario->existencias -= $item->cantidad;
                        //$inventario->save();
                    }
                    //      }

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



            /*
    
             Conexion a hacienda (Recepcion DTE)
             TEST: https://apitest.dtes.mh.gob.sv/fesv/recepciondte
             PROD: https://api.dtes.mh.gob.sv/fesv/recepciondte
    
           */



            //llaamar la funcion para crear el json 
            if ($venta->tipo_documento == 3) {
                $jsonData = $this->JsonSujeto($id);
            } else {
                $jsonData = $this->obtenerJson($id);
            }
            $json = $jsonData->getData();


            $JsonFirmar = !empty($json) ? $json[0] : null;
            //Obteenr los datos para firmar
            $userFirmador = DteAuth::get()->first();


            //VERIFICAR SI ES CONTINGENCIA O NO
            if($dte->tipo_transmision == 1){
            

            try {
                $passwordDesencriptada = Crypt::decrypt($userFirmador->passwordPri);
            } catch (DecryptException $e) {
                return response()->json(['error' => 'Error al desencriptar la contraseña.'], 400);
            }

            //API PARA FIRMAR DOCUMENTO
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('http://127.0.0.1:8113/firmardocumento/', [
                'nit' => $userFirmador->user,
                'activo' => $userFirmador->activo ? 'true' : 'false',
                'passwordPri' => $passwordDesencriptada,
                'dteJson' => $JsonFirmar,
            ]);

            if ($response->failed()) {
                // Maneja la respuesta fallida
                return response()->json(['error' => 'Error al enviar la solicitud.'], 500);
            }

            //Obtener el Body
            $response = $response->json();
            $body = $response['body'];
            //Guardar el BODY en campo firma
            $dte->firma = $body;
            $dte->save();
            /***********************************DOCUMENTO FIRMADO HASTA AQUI ****************************** */


            //API PARA ENVIAR DOCUMENTO
            if ($dte->ambiente == 1) {
                $ambienteEnvio = '00';
            } else {
                $ambienteEnvio = '01';
            }


            //API PARA ENVIAR DOCUMENTO A HACIENDA
            $EnviarFactura = Http::withHeaders([
                'Authorization' => $userFirmador->token,
                'Content-Type' => 'application/json',
            ])->post('https://apitest.dtes.mh.gob.sv/fesv/recepciondte', [
                'ambiente' => $ambienteEnvio,
                'idEnvio' => $dte->id,
                'version' => $dte->version,
                'tipoDte' => $dte->tipo->codigo,
                'documento' => $dte->firma,
                'codigoGeneracion' => $dte->codigo_generacion,
            ]);

            if ($EnviarFactura->failed()) {
                // Maneja la respuesta fallida
                return response()->json([
                    'error' => 'Error al enviar la solicitud.',
                    'respuestaHacienda' => $EnviarFactura->json(),
                    'dte' => $dte,

                ], 500);
            }

            $respuestaHacienda = $EnviarFactura->json();
            // Verificar si la respuesta de Hacienda fue exitosa
            $respuestaHacienda = $EnviarFactura->json();
            // Verificar si la respuesta de Hacienda fue exitosa
            if ($respuestaHacienda['estado'] === 'PROCESADO') {
                $dte->sello_recepcion = $respuestaHacienda['selloRecibido'];
                $dte->save();
            }
      
            /* ***********************************DOCUMENTO ENVIADO HASTA AQUI ****************************** */
            //Llamar el metodo para crear la factura
            $factura = $this->ventasController->descargarFactura($id);
            $contenidoPDF = $factura->getContent();

            //llaamar la funcion para crear el json YA CON SELLO
            if ($venta->tipo_documento == 3) {
                $jsonData = $this->JsonSujeto($id);
            } else {
                $jsonData = $this->obtenerJson($id);
            }
            $json = $jsonData->getData();
            $JsonFirmar = !empty($json) ? $json[0] : null;

          

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
                        json_encode($JsonFirmar)
                    )
                );
            } else {
                // Si el correo es inválido, registrar un aviso sin interrumpir la transacción
                Log::warning('El correo electrónico es incorrecto: ' . $correoElectronico);
            }

        } else {
            //SI ES CONTINGENCIA SE ENVIA EL CORREO PERO NO SE TRABSMITE LA FACTURA

            $factura = $this->ventasController->descargarFactura($id);
            $contenidoPDF = $factura->getContent();

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
                         json_encode($JsonFirmar)
                     )
                 );
             } else {
                 // Si el correo es inválido, registrar un aviso sin interrumpir la transacción
                 Log::warning('El correo electrónico es incorrecto: ' . $correoElectronico);
             }
        
        }

         DB::commit();

         return response()->json([
            'message' => 'DTE creado exitosamente',
            'data' => $dte,
            'detalle' => $detalle,
           // 'respuestaHacienda' => $EnviarFactura->json(),
        ], 201);

         

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al realizar la facturacion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    /**INVALIDAR UN DTE */
    public function agregarInvalidacion(Request $request, $idVenta)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
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

        // Iniciar la transacción
        DB::begintransaction();
        try {
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

            $created_at = Carbon::parse($dte->fecha);

            if ($dte->tipo_documento != 1 && $created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'message' => 'El plazo para anular este DTE ha finalizado.',
                ], 400);
            }

            // Crear el registro de anulación en la tabla VentasAnuladas
            $anulacion = VentasAnuladas::create([
                'fecha' => now()->toDateString(),
                'tipo_invalidacion_id' => $request->tipo_invalidacion_id,
                'motivo_invalidacion' => $request->motivo_invalidacion,
                'responsable_id' => $request->responsable_id,
                'solicitante_id' => $request->solicitante_id,
                'codigo_generacion_reemplazo' => $request->codigo_generacion_reemplazo,
            ]);

            // Asignar el ID de la anulación al DTE y guardar cambios
            $dte->anulada_id = $anulacion->id;
            $dte->save();

            // Cambiar el estado de la venta a 'Anulada'
            if ($venta) {
                $venta->estado = 'Anulada';
                $venta->save();
            }

            // Crear el JSON para anular la factura
            $jsonData = $this->AnularFactura($idVenta);
            $json = $jsonData->getData();
            $JsonFirmar = !empty($json) ? $json[0] : null;


            $userFirmador = DteAuth::get()->first();
            //API PARA ENVIAR DOCUMENTO
            if ($dte->ambiente == 1) {
                $ambienteEnvio = '00';
            } else {
                $ambienteEnvio = '01';
            }


            try {
                $passwordDesencriptada = Crypt::decrypt($userFirmador->passwordPri);
            } catch (DecryptException $e) {
                return response()->json(['error' => 'Error al desencriptar la contraseña.'], 400);
            }

            //API PARA FIRMAR DOCUMENTO
            $firma = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('http://127.0.0.1:8113/firmardocumento/', [
                'nit' => $userFirmador->user,
                'activo' => $userFirmador->activo ? 'true' : 'false',
                'passwordPri' => $passwordDesencriptada,
                'dteJson' => $JsonFirmar,
            ]);

            //Obtener el Body
            $responseFirma = $firma->json();
            $body = $responseFirma['body'];
            //Guardar el BODY en campo firma
            $anulacion->firma = $body;
            $anulacion->codigo_generacion = $JsonFirmar->identificacion->codigoGeneracion;
            $anulacion->save();
            

            $response = Http::withHeaders([
                'Authorization' => $userFirmador->token,
                'Content-Type' => 'application/json',
            ])->post('https://apitest.dtes.mh.gob.sv/fesv/anulardte', [
                'ambiente' => $ambienteEnvio,
                'idEnvio' => $dte->id,
                'version' => 2,//$dte->version,
                'tipoDte' => $dte->tipo->codigo,
                'documento' => $body,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Error al enviar la solicitud.',
                    'respuestaHacienda' => $response->json(),
                    'Json' => $JsonFirmar,

                ], 500);
            }

            $respuestaHacienda = $response->json();
            if ($respuestaHacienda['estado'] === 'PROCESADO') {
                $anulacion->sello_recepcion = $respuestaHacienda['selloRecibido'];
                $anulacion->save();
            }

            DB::commit();
            // Retornar una respuesta exitosa
            return response()->json([
                'message' => 'Invalidación agregada correctamente',
                'Json' => $JsonFirmar,
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


    public function TransmitirContingencia($idcont)
    {
     
        DB::beginTransaction();
        try {
            // Buscar la contingencia en la base de datos
            $contingencia = Contingencia::find($idcont);
            // Verificar si la contingencia existe
            if (!$contingencia) {
                return response()->json([
                    'message' => 'Contingencia no encontrada',
                ], 404);
            }
    

            //LLamar la funcion del json para contingencia
            $jsonData = $this->Contingencia($idcont);
            $json = $jsonData->getData();
            $JsonFirmar = !empty($json) ? $json[0] : null;


            $userFirmador = DteAuth::get()->first();
        

            try {
                $passwordDesencriptada = Crypt::decrypt($userFirmador->passwordPri);
            } catch (DecryptException $e) {
                return response()->json(['error' => 'Error al desencriptar la contraseña.'], 400);
            }

            //API PARA FIRMAR DOCUMENTO
            $firma = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('http://127.0.0.1:8113/firmardocumento/', [
                'nit' => $userFirmador->user,
                'activo' => $userFirmador->activo ? 'true' : 'false',
                'passwordPri' => $passwordDesencriptada,
                'dteJson' => $JsonFirmar,
            ]);

            //Obtener el Body
            $responseFirma = $firma->json();
            $body = $responseFirma['body'];
            //Guardar el BODY en campo firma
            $contingencia = Contingencia::find($idcont);
            $contingencia->firma = $body;
            $contingencia->codigo_generacion = $JsonFirmar->identificacion->codigoGeneracion;
            $contingencia->save();

            // ENVIAR LA CONTINGENCIA
            $response = Http::withHeaders([
                'Authorization' => $userFirmador->token,
                'Content-Type' => 'application/json',
            ])->post('https://apitest.dtes.mh.gob.sv/fesv/contingencia', [
                'nit' => $userFirmador->user,
                'documento' => $body,
            ]);


            // Verificar si la respuesta de la API es válida y contiene 'estado'
           $respuestaMH = $response->json() ?? null;
            if (isset($respuestaMH['estado']) && $respuestaMH['estado'] === 'RECIBIDO') {
                $contingencia->sello_recepcion = $respuestaMH['selloRecibido'];
                $contingencia->save();
                
            } else {
                return response()->json([
                    'error' => 'No se pudo enviar la contingencia.',
                    'respuestaHacienda' => $respuestaMH,
                    'Json' => $JsonFirmar,
                    'firmado' => $firma,
                ], 500);
           }


            $contingencia->update([
                'estado_contingencia' => 0,
            ]);
            $contingencia->save();

            DB::commit();

            /*   HASTA AQUI YA SE ENVIO EL EVENTO DE CONTINGENA
             
            AHORA HAY QUE ENVIAR ESE LOTE DE DTES QUE ESTAN EN CONTINGENCIA
            */
            $dtes = DTE::where('contingencia_id', $idcont)->get();


            // Devolver la respuesta en formato JSON
            return response()->json([
                'message' => 'los DTEs fueron emitidos correctamente',
                'respuestaHacienda' => $response->json(),
                'data' => $json,
            ], 200);


        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error al enviar la solicitud.',
                'e' => $e->getMessage(),
                //'respuestaHacienda' => $respuestaMH,
                //'Json' => $JsonFirmar,
            ], 500);
        }
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
                'tipoDocumento' => isset($cliente->identificacion) ? (string) $cliente->identificacion->codigo : null,
                'numDocumento' => isset($cliente->numeroDocumento) ? str_replace('-', '', $cliente->numeroDocumento) : null,
                'nrc' => null,
                'nombre' => trim("{$cliente->nombres} {$cliente->apellidos}"),
                'codActividad' => isset($cliente->economicActivity) ? (string) $cliente->economicActivity->codigo : null,
                'descActividad' => isset($cliente->economicActivity) ? (string) $cliente->economicActivity->actividad : null,
                'direccion' => [
                    'departamento' => isset($cliente->department) ? $cliente->department->codigo : null,
                    'municipio' => isset($cliente->municipality) ? $cliente->municipality->codigo : null,
                    'complemento' => $cliente->direccion ?? '000'
                ],
                'telefono' => $cliente->telefono ?? '00000000',
                'correo' => $cliente->correoElectronico ?? null
            ];
            
        }
        if ($dte->tipo_documento == 2) {
            //si es credito fiscal lleva esta estructura
            $receptor = [
                'nit' => str_replace('-', '', $cliente->numeroDocumento),
                'nrc' => str_replace('-', '', $cliente->nrc),
                'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'codActividad' => (string) $cliente->economicActivity->codigo ?? null,
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
                        'valor' => (float) number_format(((($ventas->total_pagar) - $totalCantidad * 0.30) / 1.13) * 0.13, 2), // IVA sobre el total del item
                    ],
                    [
                        'codigo' => 'D1',
                        'descripcion' => 'FOVIAL ($0.20 Ctvs. por galón)',
                        'valor' => (float) number_format($totalCantidad * 0.20, 2), // FOVIAL por cantidad de galones
                    ],
                    [
                        'codigo' => 'C8',
                        'descripcion' => 'COTRANS ($0.10 Ctvs. por galón)',
                        'valor' => (float) number_format($totalCantidad * 0.10, 2), // COTRANS por cantidad de galones
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
                        'valor' => number_format($totalCantidad * 0.20, 2),
                    ],
                    [
                        'codigo' => 'C8',
                        'descripcion' => 'COTRANS ($0.10 Ctvs. por galón)',
                        'valor' => number_format($totalCantidad * 0.10, 2),
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
                        'valor' => (float) number_format(($ventas->total_pagar / 1.13) * 0.13, 2), // IVA sobre el total del item
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
                $precioNetoTot  = $dte->tipo_documento == 2 ? number_format(($det->total - ($det->cantidad * 0.3)) / 1.13, 2) : number_format($det->total - $det->cantidad * 0.3, 4);
                $totalGravadas = $dte->tipo_documento == 2 ? number_format(($ventas->total_pagar - $totalCantidad * 0.3) / 1.13, 2) : number_format(($ventas->total_pagar - $totalCantidad * 0.30), 4);
            } else {
                $precioNetoUni = $dte->tipo_documento == 2 ? number_format($det->precio / 1.13, 2) : number_format($det->precio, 2);
                $precioNetoTot = $dte->tipo_documento == 2 ? number_format($det->total / 1.13, 2) : number_format($det->total, 2);
                $totalGravadas = $dte->tipo_documento == 2 ? number_format($ventas->total_pagar / 1.13, 2) : number_format($ventas->total_pagar, 2);
            }

            // Crear el cuerpo del documento para el producto
            $cuerpoDocumento[] = [
                'numItem' => $conta++,
                'tipoItem' => $det->producto->producto->tipo_producto_id,
                'numeroDocumento' => null,
                'cantidad' => $det->cantidad,
                'codigo' => (string) $det->producto->producto_id,
                'codTributo' => null,
                'uniMedida' => $det->producto->unidad->codigo,
                'descripcion' => $det->producto->nombreProducto,
                'precioUni' => (float) $precioNetoUni, // $dte->tipo_documento == 2 ? number_format($det->precio / 1.13, 4) : number_format($det->precio, 4),
                'montoDescu' => 0,
                'ventaNoSuj' => 0,
                'ventaExenta' => 0,
                'ventaGravada' => (float) str_replace(',', '', $precioNetoTot), //$dte->tipo_documento == 2 ? number_format($det->total / 1.13, 4) : number_format($det->total, 4),
                'tributos' => $tributos,
                'psv' => 0,
                'noGravado' => 0,
            ];

            // Agregar ivaItem si el tipo de documento es 1
            if ($dte->tipo_documento == 1) {
                $cuerpoDocumento[count($cuerpoDocumento) - 1]['ivaItem'] = (float) number_format(($det->total / 1.13) * 0.13, 4);
            }
            if ($dte->tipo_documento == 1 && $det->producto->producto->combustible) {
                $cuerpoDocumento[count($cuerpoDocumento) - 1]['ivaItem'] = (float) number_format((($det->total - $det->cantidad * 0.3) / 1.13) * 0.13, 2);
            }
        }
        $resumen = [
            'totalNoSuj' => 0,
            'totalExenta' => 0,
            'totalGravada' => (float) str_replace(',', '', $totalGravadas),
            'subTotalVentas' => (float) str_replace(',', '', $totalGravadas),
            'descuNoSuj' => 0,
            'descuExenta' => 0,
            'descuGravada' => 0,
            'porcentajeDescuento' => 0,
            'totalDescu' => 0,
            'tributos' => $tributosDetallados,
            'subTotal' => (float) str_replace(',', '', $totalGravadas),
            'ivaRete1' => 0,
            'reteRenta' => 0,
            'montoTotalOperacion' => (float) $ventas->total_pagar,
            'totalNoGravado' => 0,
            'totalPagar' => (float) $ventas->total_pagar,
            'totalLetras' => $totalEnLetras,
        ];

        // Solo agregar 'ivaPerci1' si 'tipo_documento' es igual a 2
        if ($dte->tipo_documento == 2) {
            $resumen['ivaPerci1'] = 0;
        }

        // Calcular y agregar totalIva después de totalLetras si el tipo de documento no es 2
        if ($dte->tipo_documento == 1) {
            $resumen['totalIva'] = $det->producto->producto->combustible
                ? (float) number_format((($ventas->total_pagar - $totalCantidad * 0.30) / 1.13) * 0.13, 2)
                : (float) number_format(($ventas->total_pagar / 1.13) * 0.13, 2);
        }

        // Continuar agregando el resto de los elementos
        $resumen += [
            'saldoFavor' => 0,
            'condicionOperacion' => $ventas->condicion,
            'pagos' => [
                [
                    'codigo' => (string) $pago->codigo,
                    'montoPago' => (float) ($ventas->condicion == 2) ? 0 : (float) $ventas->total_pagar,
                    'referencia' => null,
                    'plazo' => ($ventas->condicion == 2) ? '01' : null,
                    'periodo' => ($ventas->condicion == 2) ? 15 : null,
                ]
            ],
            'numPagoElectronico' => ''
        ];


        // Estructura completa del JSON
        $jsonData = [
            'identificacion' => [
                'version' => $dte->version,
                'ambiente' => $Codambiente,
                'tipoDte' => $dte->tipo->codigo,
                'numeroControl' => $dte->numero_control,
                'codigoGeneracion' => $dte->codigo_generacion,
                'tipoModelo' => $dte->modelo_facturacion,
                'tipoOperacion' => $dte->tipo_transmision,
                'tipoContingencia' => $dte->contingencia->tipo_contingencia_id ?? null,
                'motivoContin' =>  $dte->motivo_contingencia ?? null,
                'fecEmi' => $dte->fecha,
                'horEmi' => $dte->hora,
                'tipoMoneda' => 'USD',
            ],
            'documentoRelacionado' => null,
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nrc' => str_replace('-', '', $emisor->nrc),
                'nombre' => $emisor->nombre,
                'codActividad' => (string) $emisor->economicActivity->codigo,
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
            // 'apendice ' => null
            'apendice' => [
                [
                    'campo' => 'Datos del documento',
                    'etiqueta' => 'Sello',
                    'valor' => $dte->sello_recepcion ?? '00',
                ]
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
                'codigo' => (string) $det->producto->producto_id,
                'uniMedida' => $det->producto->unidad->codigo,
                'descripcion' => $det->producto->nombreProducto,
                'precioUni' => (float) $det->precio,
                'montoDescu' => 0,
                'compra' => (float) str_replace(',', '', $det->total),

            ];
        }

        $jsonData = [
            'identificacion' => [
                'version' => $version,
                'ambiente' => $Codambiente,
                'tipoDte' => $dte->tipo->codigo ?? null,
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
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nrc' => str_replace('-', '', $emisor->nrc),
                'nombre' => $emisor->nombre,
                'codActividad' => (string) $emisor->economicActivity->codigo,
                'descActividad' => $emisor->economicActivity->actividad,
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
            'sujetoExcluido' => [
                'tipoDocumento' => (string) $cliente->identificacion->codigo ?? null,
                'numDocumento' => str_replace('-', '', $cliente->numeroDocumento),
                'nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'codActividad' => (string) $cliente->economicActivity->codigo ?? null,
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
                'totalCompra' => (float) str_replace(',', '', $ventas->total_pagar + $ventas->retencion),
                'descu' => 0,
                'totalDescu' => 0,
                'subTotal' => (float) str_replace(',', '', $ventas->total_pagar + $ventas->retencion),
                'ivaRete1' => 0,
                'reteRenta' => (float) $dte->ventas->retencion ?? 0,
                'totalPagar' => (float) str_replace(',', '', $ventas->total_pagar),
                'totalLetras' => $totalEnLetras,
                'condicionOperacion' => $ventas->condicion,
                'pagos' => null,
                'observaciones' => ""
            ],
            "apendice" => [
                [
                    "campo" => "Datos del documento",
                    "etiqueta" => "Sello",
                    "valor" => $dte->sello_recepcion ?? '00',
                ]
            ]

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
                'version' => 2, //$dte->version,
                'ambiente' => $Codambiente,
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()),
                'fecAnula' => now()->format('Y-m-d'),
                'horAnula' => now()->format('H:i:s')
            ],
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nombre' =>  $emisor->nombre, //$emisor->nombre,
                //'numFacturador' => $emisor->num_facturador,
                'tipoEstablecimiento' => (string) $emisor->establecimiento->codigo,
                //'codEstablecimiento' => null,
                'nomEstablecimiento' => $emisor->nombre_establecimiento, //ver
                'codEstableMH' => "M001",
                'codEstable' => null,
                'codPuntoVentaMH' => "P001",
                'codPuntoVenta' => null,
                // 'puntoVenta' => null,
                'telefono' => $emisor->telefono,
                'correo' => $emisor->correo,
            ],
            'documento' => [
                'tipoDte' => (string) $dte->tipo->codigo,
                'codigoGeneracion' => $dte->codigo_generacion,
                'selloRecibido' => $dte->sello_recepcion ?? '',
                'numeroControl' => $dte->numero_control,
                'fecEmi' => $dte->fecha,
                'montoIva' => (float) str_replace(',', '', $dte->ventas->tipo_documento == 1 ? number_format(($dte->ventas->total_pagar / 1.13) * 0.13, 2) : number_format(($dte->ventas->total_pagar / 1.13) * 0.13, 2)),
                'codigoGeneracionR' => $dte->anulada->codigo_generacion_reemplazo ?? null,
                'tipoDocumento' =>  isset($cliente->identificacion) ? (string) $cliente->identificacion->codigo : '13',
                'numDocumento' => isset($cliente->identificacion) ? str_replace('-', '', $cliente->numeroDocumento) : '000000000',
                'nombre' =>  $cliente->nombres . ' ' . $cliente->apellidos,
                'telefono' => $cliente->telefono ?? null,
                'correo' => $cliente->correoElectronico ?? null
            ],
            'motivo' => [
                'tipoAnulacion' => $dte->anulada->tipo_invalidacion_id,
                'motivoAnulacion' =>  $dte->anulada->motivo_invalidacion ?? null,
                'nombreResponsable' => $dte->anulada->responsableAnular->nombre,
                'tipDocResponsable' => (string) $dte->anulada->responsableAnular->tipoDocumento->codigo ?? null,
                'numDocResponsable' => str_replace('-', '', $dte->anulada->responsableAnular->numero_documento),
                'nombreSolicita' =>  $dte->anulada->solicitanteAnular->nombres . ' ' . $dte->anulada->solicitanteAnular->apellidos,
                'tipDocSolicita' =>  isset($cliente->identificacion) ? (string) $cliente->identificacion->codigo : '13',
                'numDocSolicita' => isset($cliente->identificacion) ? str_replace('-', '', $cliente->numeroDocumento) : '000000000',
            ]
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
                'codigoGeneracion' => $det->codigo_generacion,
                'tipoDoc' => $det->tipo->codigo,
            ];
        }



        $jsonData = [
            'identificacion' => [
                'version' => $primerDTE->version,
                'ambiente' => $Codambiente,
                'codigoGeneracion' => strtoupper(Str::uuid()->toString()), //$dte->contingencia->codigo_generacion,
                'fTransmision' => now()->format('Y-m-d'),
                'hTransmision' => now()->format('H:i:s')
            ],
            'emisor' => [
                'nit' => str_replace('-', '', $emisor->nit),
                'nombre' => $emisor->nombre,
                'nombreResponsable' => 'Iris Alonzo',// $dte->contingencia->responsable->nombre,
                'tipoDocResponsable' => '13',//(string) $dte->contingencia->responsable->tipoDocumento->codigo,
                'numeroDocResponsable' => '058246503',// str_replace('-', '', $dte->contingencia->responsable->numero_documento),
                'tipoEstablecimiento' => $emisor->establecimiento->codigo,
                'codEstableMH' => null,
                'codPuntoVenta' => null,
                'telefono' => $emisor->telefono,
                'correo' => strtoupper($emisor->correo),
            ],
            'detalleDTE' => $detalleDTE,
            'motivo' => [
                'fInicio' => $primerDTE->contingencia->fechaInicio ?? null,
                'fFin' => $primerDTE->contingencia->fechaFin ?? null,
                'hInicio' => $primerDTE->contingencia->horaInicio ?? null,
                'hFin' => $primerDTE->contingencia->horaFin ?? null,
                'tipoContingencia' => $primerDTE->contingencia->tipoContingencia->id ?? null,
                'motivoContingencia' => $primerDTE->contingencia->motivo_contingencia ?? null,
            ]
        ];

        return response()->json([$jsonData]);
    }
}
