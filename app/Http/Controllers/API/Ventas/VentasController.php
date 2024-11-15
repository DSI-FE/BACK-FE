<?php

namespace App\Http\Controllers\API\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventarios\Inventario;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Ventas\DetalleVenta;
//use BaconQrCode\Encoder\QrCode;
use Illuminate\Support\Facades\DB;
use NumberToWords\NumberToWords;
use App\Helpers\StringsHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class VentasController extends Controller
{

    //Funcion para obtener todas las ventas
    public function index(Request $request)
    {
        // Reglas de validación para los parámetros de consulta
        $rules = [
            'search' => ['nullable', 'max:250'],
            'perPage' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable'],
            'sort.key' => ['nullable', Rule::in(['id', 'created_at', 'cliente_id', 'total'])],
            'sort.order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];

        $messages = [
            'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
            'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
            'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
            'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
            'sort.order.in' => 'El valor de ordenamiento es inválido.',
        ];

        // Validar los parámetros de consulta
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener los parámetros de consulta
        $search = StringsHelper::normalizarTexto($request->query('search', ''));
        $perPage = $request->query('perPage', 5);

        $sort = json_decode($request->input('sort'), true);
        $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
        $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'desc';

        // Obtener las ventas con filtrado y ordenamiento
        $ventas = Venta::with(['cliente', 'condicion', 'tipo_documento'])
            ->where(function (Builder $query) use ($search) {
                return $query->whereHas('cliente', function (Builder $q) use ($search) {
                    $q->where('nombres', 'like', '%' . $search . '%')
                        ->orWhere('apellidos', 'like', '%' . $search . '%');
                })
                    ->orWhere('total_pagar', 'like', '%' . $search . '%');
            })
            ->orderBy($orderBy, $orderDirection)
            ->paginate($perPage);

        // Recorrer las ventas para obtener el DTE asociado
        $ventasConDTE = $ventas->map(function ($venta) {
            $dte = DTE::where('id_venta', $venta->id)->first();
            $venta->codigo_generacion = $dte->codigo_generacion ?? '';
        });



        // Preparar la respuesta en formato JSON
        $response = $ventas->toArray();
        $response['search'] = $request->query('search', '');
        $response['sort'] = [
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection,
        ];

        return response()->json($response, 200);
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
                'tipo_pago_id' => 1,
                'retencion' => $request->retencion,
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
            DetalleVenta::where('venta_id', $venta->id)->forcedelete();

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
        $dte = DTE::with('ventas',  'ambiente', 'moneda', 'tipo', 'tipoTransmision', 'modeloFacturacion')->where('id_venta', $id)->first();

        // Obtener los detalles de la venta
        $detalle = DetalleVenta::with('producto')
            ->where('venta_id', $id)
            ->get();
        // Esto es para obtener todos los clientes junto con sus relaciones utilizando Eloquent ORM
        $emisor = Emisor::with(['department', 'municipality', 'economicActivity'])
            ->where('id', 1)->first();

        //imagen
        $imagePath = storage_path('app/public/logos/LANDOS.png');
        $anuladaPath = storage_path('app/public/logos/anulado.png');


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
        //URL que va a contener el qr
        $url = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $codigoAmbiente . '&codGen=' . $dte->codigo_generacion . '&fechaEmi=' . $dte->fecha;

        //RUTA DEL QR ESTATICO
        $imageQR = storage_path('app/public/QRCODES/' . $dte->qr_code);


        //Diseño del pdf
        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->AddPage();
        // Definir el color del borde (RGB)
        $pdf->SetDrawColor(152, 155, 155);

        // Dibujar un rectángulo alrededor de la página
        $borderMargin = 5; // Margen del borde
        $pdf->Rect($borderMargin, $borderMargin, $pdf->getPageWidth() - 2 * $borderMargin, $pdf->getPageHeight() - 2 * $borderMargin, 'D');

        if ($dte->qr_code) {
            $pdf->Image($imagePath, 10, $pdf->GetY() + 1, 43, 0, '', '', '', false, 300, '', false, false, 0, 'L', false);
        } else {
            $pdf->Image($imageQR, 93, 23, 25, 25, 'PNG', '', '', false, 300, '', false, false, 0);
        }
        // Inserta el logo alineado a la izquierda
        $pdf->Image($imagePath, 10, $pdf->GetY() + 1, 43, 0, '', '', '', false, 300, '', false, false, 0, 'L', false);

        // Mueve el cursor hacia la derecha para centrar "DOCUMENTO TRIBUTARIO ELECTRONICO" en la misma línea
        $pdf->SetFont('times', 'B', 13);
        $pdf->SetX(60); // Ajusta X para que quede centrado
        $pdf->Cell(97, 5, 'DOCUMENTO TRIBUTARIO ELECTRONICO', 0, 0, 'C');

        // Coloca "Ver 3" a la derecha en la misma línea
        $pdf->SetX(-30); // Ajusta X para alinearlo a la derecha
        $pdf->Cell(0, 5, 'version. ' . $dte->version, 0, 1, 'R');
        $pdf->Cell(0, 5, $dte->tipo->nombre, 0, 1, 'C');


        // Establecer la fuente en negrita para los títulos
        $pdf->SetFont('Times', 'B', 10);

        // Texto a la izquierda
        $pdf->Cell(55, 10, 'Codigo de generación', 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía para posicionar la imagen en el centro
        // Inserta la imagen en el centro de la celda
        $pdf->Image($imageQR, $pdf->GetX() - 42, $pdf->GetY() + 3, 30, 30, '', '', '', false, 300, '', false, false, 0, 'C', false);

        // Texto a la derecha
        $pdf->Cell(200, 10, 'Modelo de Facturación: ', 0, 0, 'L');

        // Agrega una nueva línea para los datos adicionales
        $pdf->Ln(7); // Añade espacio vertical entre líneas

        // Establecer la fuente normal para el contenido
        $pdf->SetFont('Times', '', 10);

        // Texto para código de generación y modelo de facturación en la misma línea
        $pdf->Cell(60, 5, $dte->codigo_generacion, 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía en el centro
        $pdf->Cell(35, 5, $dte->modeloFacturacion->nombre, 0, 1, 'R');
        $pdf->Ln(1); // Añade espacio vertical entre líneas

        // Establecer la fuente en negrita para los títulos
        $pdf->SetFont('Times', 'B', 10);

        // Texto a la izquierda
        $pdf->Cell(60, 5, 'Número de control', 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía
        $pdf->Cell(30, 5, 'Tipo de Transmisión: ', 0, 1, 'R');

        // Establecer la fuente normal para el contenido
        $pdf->SetFont('Times', '', 10);

        // Texto para número de control y tipo de transmisión en la misma línea
        $pdf->Cell(60, 5, $dte->numero_control, 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía en el centro   
        if ($dte->tipo_transmision == '1') {
            $pdf->Cell(26, 5, $dte->tipoTransmision->nombre, 0, 1, 'R');
        } else {
            $pdf->Cell(38, 5, $dte->tipoTransmision->nombre, 0, 1, 'R');
        }
        // $pdf->Cell(26, 5, $dte->tipoTransmision->nombre, 0, 1, 'R');
        $pdf->Ln(1); // Añade espacio vertical entre líneas

        // Establecer la fuente en negrita para los títulos
        $pdf->SetFont('Times', 'B', 10);

        // Texto a la izquierda
        $pdf->Cell(60, 5, 'Sello de recepción', 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía
        $pdf->Cell(40, 5, 'Fecha y hora de generación: ', 0, 1, 'R');

        // Establecer la fuente normal para el contenido
        $pdf->SetFont('Times', '', 10);

        // Texto para sello de recepción y fecha/hora de generación en la misma línea
        $pdf->Cell(60, 5, $dte->sello_recepcion, 0, 0, 'L');
        $pdf->Cell(70, 5, '', 0, 0, 'C'); // Celda vacía en el centro
        $pdf->Cell(27, 5, $dte->fecha . ' ' . $dte->hora, 0, 1, 'R');



        // Definir el contenido de la tabla
        /*   $tablaDTE = '
<table border="0" cellspacing="2" cellpadding="2" width="100%; ">
    <tr>
        <td style="text-align: left; width: 55%; font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
            <p><strong>Código de generación:</strong> <br>' . $dte->codigo_generacion . '</p>
            <p><strong>Número de control:</strong> <br>' . $dte->numero_control . '</p>
            <p><strong>Sello de recepción:</strong> <br>' . $dte->sello_recepcion . '</p>
        </td>
        <td style="text-align: left; width: 55%; font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
            <p><strong>Modelo de facturación:</strong> <br>' . $dte->modeloFacturacion->nombre . '</p>
            <p><strong>Tipo de transmisión:</strong> <br>' . $dte->tipoTransmision->nombre . '</p>
            <p><strong>Fecha y hora de generación:</strong> <br>' . $dte->fecha . ' ' . $dte->hora . '</p>
        </td>
    </tr>
</table>';

        // Escribir la tabla en el PDF
        $pdf->writeHTML($tablaDTE, true, false, true, false, '');*/

        $x = 11;
        $y = 65;
        $width = 88;
        $height = 10;
        $radius = 10;

        // Definir color de relleno y borde
        $fillColor = array(220, 220, 220);  // Gris claro
        $borderColor = array(0, 0, 0);      // Negro

        // Establecer el color de relleno
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

        // Establecer el color de borde
        $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        // Dibuja el rectángulo redondeado
        $pdf->RoundedRect($x, $y, $width, $height, $radius, '0101', 'DF', array('all' => array('width' => 0.5, 'color' => '#DCDCDC')));


        // Establecer la fuente en negrita
        $pdf->SetFont('Times', 'B', 12); // 'B' indica negrita, y el tamaño de la fuente es 12

        // Agregar texto dentro del rectángulo en mayúsculas y negrita
        $pdf->SetXY($x, $y + 3);
        $pdf->Cell($width, 2, strtoupper('Emisor'), 0, 1, 'C');

        // Volver a la fuente normal si necesitas más texto después
        $pdf->SetFont('Times', '', 10); // Cambia a fuente normal

        // Ajusta la posición Y para la tabla
        $y += $height + 1; // Un poco de espacio entre el rectángulo y la tabla

        // Define el contenido de la tabla
        $tablaEmisor = '<br><br>
<table style="font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
   
    <tr>
        <td style="font-family: \'Times New Roman\', Times, serif; font-weight: bold; font-size: 12px;">' . $emisor->nombre . '</td>
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
        <td>Tipo de establecimiento: ' . $emisor->establecimiento->valores . '</td>
    </tr>
</table>';

        $x = 112;
        $y = 65;
        $width = 88;
        $height = 10;
        $radius = 10;

        // Definir color de relleno y borde
        $fillColor = array(220, 220, 220);  // Gris claro
        $borderColor = array(0, 0, 0);      // Negro

        // Establecer el color de relleno
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

        // Establecer el color de borde
        $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        // Dibuja el rectángulo redondeado
        $pdf->RoundedRect($x, $y, $width, $height, $radius, '0101', 'DF', array('all' => array('width' => 0.5, 'color' => '#DCDCDC')));


        // Establecer la fuente en negrita
        $pdf->SetFont('Times', 'B', 12); // 'B' indica negrita, y el tamaño de la fuente es 12

        // Agregar texto dentro del rectángulo en mayúsculas y negrita
        $pdf->SetXY($x, $y + 3);
        $pdf->Cell($width, 2, strtoupper('Receptor'), 0, 1, 'C');

        // Volver a la fuente normal si necesitas más texto después
        $pdf->SetFont('Times', '', 10); // Cambia a fuente normal

        // Ajusta la posición Y para la tabla
        $y += $height + 1; // Un poco de espacio entre el rectángulo y la tabla

        $tablaCliente = '<br><br>
<table style=" font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
    <tr>
         <th style="font-family: \'Times New Roman\', Times, serif; font-weight: bold; font-size: 12px;">' . $dte->ventas->cliente_nombre . '</th>
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

        // Escribir la tabla del Emisor (a la izquierda)
        $pdf->writeHTMLCell(90, '', 10, '', $tablaEmisor, 0, 0, 0, true, 'L', true);

        // Escribir la tabla del Cliente (a la derecha)
        $pdf->writeHTMLCell(90, '', 110, '', $tablaCliente, 0, 1, 0, true, 'L', true);

        $x = 11;
        $y = 135;
        $width = 190;
        $height = 10;
        $radius = 10;

        // Definir color de relleno y borde
        $fillColor = array(220, 220, 220);  // Gris claro
        $borderColor = array(0, 0, 0);      // Negro

        // Establecer el color de relleno
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

        // Establecer el color de borde
        $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        // Dibuja el rectángulo redondeado
        $pdf->RoundedRect($x, $y, $width, $height, $radius, '0101', 'DF', array('all' => array('width' => 0.5, 'color' => '#DCDCDC')));


        // Establecer la fuente en negrita
        $pdf->SetFont('Times', 'B', 10); // 'B' indica negrita, y el tamaño de la fuente es 12

        // Agregar texto dentro del rectángulo en mayúsculas y negrita
        $pdf->SetXY($x + 100, $y);
        $pdf->Cell($width, 5, '             Precio        Desc      Ventas       Ventas      Ventas', 0, 1, 'L');
        $pdf->Cell($width, 5, '  N°       Cantidad          Descripcion                                                  Unidad   Unitario      Item     no sujetas   exentas   gravadas', 0, 1, 'L');
        // Volver a la fuente normal si necesitas más texto después
        $pdf->SetFont('Times', '', 10); // Cambia a fuente normal

        // Ajusta la posición Y para la tabla
        $y += $height + 5; // Un poco de espacio entre el rectángulo y la tabla




        /*   $tablaContenido = ' <br><br><br>
<table style="border-collapse: collapse; width: 100%;  font-size: 10px; font-family: \'Times New Roman\', Times, serif;">
    <tr style="background-color: #DCDCDC; text-align: center; font-weight: bold">
         <th style="width: 23px;">N°</th>
         <th style="width: 46px;">Cantidad</th>
         <th style="width: 200px;">Descripción</th>
         <th style="width: 42px;">Unidad</th>
         <th style="width: 42px;">Precio Unitario</th>
         <th style="width: 42px;">Desc Item</th>
         <th style="width: 47px;">Ventas no sujetas</th>
         <th style="width: 42px;">Ventas Exentas</th>
         <th style="width: 50px;">Ventas Gravadas</th>
    </tr>';*/
        $tablaContenido = '<table style="border-collapse: collapse; width: 100%;  font-size: 10px; font-family: \'Times New Roman\', Times, serif;">';
        // Iteramos sobre el array para agregar los productos a la tabla
        $numero = 1;
        $cantidades = 0;
        $diesel = 0;

        // Iterar solo sobre los productos
        foreach ($detalle as $item) {
            //Si es CCF y Diesel
            if ($dte->tipo_documento == 2 && $item['producto']['producto']['combustible']) {
                $tablaContenido .= '
            <tr style="font-size: 9px; text-align: center ">
               <td style="height: 15px; width: 23px">' . $numero++ . '</td>
                 <td  style="width: 50px;">' . $item['cantidad'] . '</td>
                 <td  style="width: 195px; text-align: left">' . $item['producto']['nombre_producto'] . '</td>
                 <td  style="width: 42px;">' . $item['producto']['unidad_medida'] . '</td>
                 <td  style="width: 42px;">$' . number_format(($item['precio'] - 0.30) / 1.13, 2) . '</td>
                 <td  style="width: 45px;">$0.00</td>
                 <td  style="width: 42px;">$0.00</td>
                 <td  style="width: 45px;">$0.00</td>
                 <td  style="width: 50px;">$' . number_format(($item['total'] - $item['cantidad'] * 0.30) / 1.13, 2) . '</td>
            </tr>';
            }
            if ($dte->tipo_documento == 1 && $item['producto']['producto']['combustible']) {
                $tablaContenido .= '
            <tr style="font-size: 9px; text-align: center">
               <td style="height: 15px; width: 23px">' . $numero++ . '</td>
                 <td  style="width: 50px;">' . $item['cantidad'] . '</td>
                 <td  style="width: 195px; text-align: left">' . $item['producto']['nombre_producto'] . '</td>
                 <td  style="width: 42px;">' . $item['producto']['unidad_medida'] . '</td>
                 <td  style="width: 42px;">$' . number_format($item['precio'] - 0.30, 2) . '</td>
                 <td  style="width: 45px;">$0.00</td>
                 <td  style="width: 42px;">$0.00</td>
                 <td  style="width: 42px;">$0.00</td>
                 <td  style="width: 50px;">$' . number_format($item['total'] - $item['cantidad'] * 0.3, 2) . '</td>
            </tr>';
            }

            //Si es CCF pero no es diesel
            if ($dte->tipo_documento == 2 && !$item['producto']['producto']['combustible']) {
                $tablaContenido .= '
        <tr style="font-size: 9px; text-align: center ">
           <td style="height: 15px; width: 23px">' . $numero++ . '</td>
             <td  style="width: 50px;">' . $item['cantidad'] . '</td>
             <td  style="width: 195px; text-align: left">' . $item['producto']['nombre_producto'] . '</td>
             <td  style="width: 42px;">' . $item['producto']['unidad_medida'] . '</td>
             <td  style="width: 42px;">$' . number_format($item['precio'] / 1.13, 2) . '</td>
             <td  style="width: 45px;">$0.00</td>
             <td  style="width: 42px;">$0.00</td>
             <td  style="width: 42px;">$0.00</td>
             <td  style="width: 50px;">$' . number_format($item['total'] / 1.13, 2) . '</td>
        </tr>';
            }
            if ($dte->tipo_documento == 1 && !$item['producto']['producto']['combustible']) {
                $tablaContenido .= '
        <tr style="font-size: 9px; text-align: center">
             <td style="height: 15px; width: 23px">' . $numero++ . '</td>
             <td style="width: 50px;">' . $item['cantidad'] . '</td>
             <td style="width: 195px; text-align: left">' . $item['producto']['nombre_producto'] . '</td>
             <td style="width: 42px;">' . $item['producto']['unidad_medida'] . '</td>
             <td style="width: 42px;">$' . number_format($item['precio'], 2) . '</td>
             <td style="width: 45px;">$0.00</td>
             <td style="width: 42px;">$0.00</td>
             <td style="width: 42px; text-align: left">$0.00</td>
             <td style="width: 50px">$' . number_format($item['total'], 2) . '</td>
        </tr>';
            }
            //SI ES FACTURA SUJETO EXCLUIDO
            if ($dte->tipo_documento == 3) {
                $tablaContenido .= '
        <tr style="font-size: 9px; text-align: center">
           <td style="height: 15px; width: 23px">' . $numero++ . '</td>
             <td  style="width: 50px;">' . $item['cantidad'] . '</td>
             <td  style="width: 195px; text-align: left">' . $item['producto']['nombre_producto'] . '</td>
             <td  style="width: 42px;">' . $item['producto']['unidad_medida'] . '</td>
             <td  style="width: 42px;">$' . number_format($item['precio'], 2) . '</td>
             <td  style="width: 45px;">$0.00</td>
             <td  style="width: 42px;">$0.00</td>
             <td  style="width: 42px;">$0.00</td>
             <td  style="width: 50px;">$' . number_format($item['total'], 2) . '</td>
        </tr>';
            }


            //calculos
            $cantidades += $item['cantidad'];
            if ($item['producto']['producto']['combustible']) {
                $diesel = 1;
            }
        }

        //PARTE DEL RESUMEN DE VENTAS
        // Condiciones
        switch ($dte->ventas->condicion) {
            case 1:
                $nombreCondicion = 'Contado';
                break;
            case 2:
                $nombreCondicion = 'A Crédito';
                break;
            case 3:
                $nombreCondicion = 'Otro';
                break;
            default:
                $nombreCondicion = '';
                break;
        }

        // Colocar la suma de ventas después del foreach
        if ($dte->tipo_documento == 2 && $item['producto']['producto']['combustible']) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . number_format(($dte->ventas->total_pagar - $cantidades * 0.30) / 1.13, 2)  . '</td>
            </tr>';
        }
        if ($dte->tipo_documento == 1 && $item['producto']['producto']['combustible']) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . number_format(($dte->ventas->total_pagar - $cantidades * 0.30), 2) . '</td>
            </tr>';
        }
        if ($dte->tipo_documento == 2 && !$item['producto']['producto']['combustible']) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . number_format($dte->ventas->total_pagar / 1.13, 2)  . '</td>
            </tr>';
        }
        if ($dte->tipo_documento == 1 && !$item['producto']['producto']['combustible']) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . number_format($dte->ventas->total_pagar, 2) . '</td>
            </tr>';
        }
        //SI ES SUJETO EXCLUIDO
        if ($dte->tipo_documento == 3) {
            $tablaContenido .= '
            <hr>
            <tr>
                <td colspan="8" style="text-align: right;">Suma de ventas:</td>
                <td colspan="1">$ ' . number_format($dte->ventas->total_pagar + $dte->ventas->retencion, 2)  . '</td>
            </tr>';
        }



        // Añadir el resto del contenido
        if ($dte->tipo_documento != 3) {
            $tablaContenido .= '';
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
        }
        // Calcular y mostrar subtotal e IVA según el tipo de documento y combustible
        if ($dte->tipo_documento == 2 && $item['producto']['producto']['combustible']) {
            $subtotal = number_format(($dte->ventas->total_pagar - $cantidades * 0.30) / 1.13, 2);
            $iva = number_format(($dte->ventas->total_pagar - $cantidades * 0.30) / 1.13 * 0.13, 2);
            $tablaContenido .= '
    <tr>
        <td colspan="8" style="text-align: right;">Subtotal:</td>
        <td colspan="1">$ ' . $subtotal . '</td>
    </tr>
    <tr>
        <td colspan="8" style="text-align: right;">IVA:</td>
        <td colspan="1">$ ' . $iva . '</td>
    </tr>';
        }

        if ($dte->tipo_documento == 1 && $item['producto']['producto']['combustible']) {
            $subtotal = number_format(($dte->ventas->total_pagar - $cantidades * 0.30), 2);
            $tablaContenido .= '
    <tr>
        <td colspan="8" style="text-align: right;">Subtotal:</td>
        <td colspan="1">$ ' . $subtotal . '</td>
    </tr>';
        }

        if ($dte->tipo_documento == 2 && !$item['producto']['producto']['combustible']) {
            $subtotal = number_format(($dte->ventas->total_pagar) / 1.13, 2);
            $iva = number_format(($dte->ventas->total_pagar / 1.13) * 0.13, 2);
            $tablaContenido .= '
    <tr>
        <td colspan="8" style="text-align: right;">Subtotal:</td>
        <td colspan="1">$ ' . $subtotal . '</td>
    </tr>
    <tr>
        <td colspan="8" style="text-align: right;">IVA:</td>
        <td colspan="1">$ ' . $iva . '</td>
    </tr>';
        }

        if ($dte->tipo_documento == 1 && !$item['producto']['producto']['combustible']) {
            $subtotal = number_format($dte->ventas->total_pagar, 2);
            $tablaContenido .= '
            <tr>
                <td colspan="8" style="text-align: right;">Subtotal:</td>
                <td colspan="1">$ ' . $subtotal . '</td>
            </tr>';
        }


        if ($diesel) {
            $tablaContenido .= '
            <tr>
                <td colspan="8" style="text-align: right;">FOVIAL ($0.20 Ctvs. por galón):</td>
                <td colspan="1">$ ' . number_format($cantidades * 0.20, 2) . '</td>
            </tr>
            <tr>
                <td colspan="8" style="text-align: right;">COTRANS ($0.10 Ctvs. por galón):</td>
                <td colspan="1">$ ' . number_format($cantidades * 0.10, 2) . '</td>
            </tr>';
        }

        if ($dte->tipo_documento != 3) {
            $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">IVA Retenido:</td>
            <td colspan="1">$ 0.00</td>
        </tr>';
        }

        if ($dte->tipo_documento == 3) {
            $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Retención de renta:</td>
            <td colspan="1">$ ' . $dte->ventas->retencion . '</td>
        </tr>';
        }

        if ($dte->tipo_documento != 3) {
            $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Monto total de la operación:</td>
            <td colspan="1">$ 0.00</td>
        </tr><br>';
        }
        //Total a pagar

        $tablaContenido .= '
        <tr>
            <td colspan="8" style="text-align: right;">Total a pagar:</td>
            <td colspan="1"><strong>$ ' . number_format($dte->ventas->total_pagar, 2) . '</strong></td>
        </tr><hr>
        
       </table>';

        //Total en letras
        $tablaContenido .= '
         <table cellpadding="5" cellspacing="0" style="font-size: 10px; font-family: \'Times New Roman\', Times, serif; background-color: #DCDCDC; text-align: center; border: 1px solid black;">
            <tr>
               <td><strong>Total en letras:</strong> ' . $totalEnLetras . '</td>
            </tr>
            <tr>
               <td><strong>Condición de la operación:</strong> ' . $nombreCondicion . '</td>
            </tr>
        </table>';




        //tabla de productos
        $pdf->writeHTMLCell('', '', '', '', $tablaContenido, 0, 1, 0, true, 'L', true);
        /*
        
        $x = 11;
        $y = $pdf->GetY() + 5;    
        $width = 190;
        $height = 15;
        $radius = 10;

        // Definir color de relleno y borde
        $fillColor = array(220, 220, 220);  // Gris claro
        $borderColor = array(0, 0, 0);      // Negro

        // Establecer el color de relleno
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

        // Establecer el color de borde
        $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);

        // Dibuja el rectángulo redondeado
        $pdf->RoundedRect($x, $y, $width, $height, $radius, '0101', 'DF', array('all' => array('width' => 0.5, 'color' => '#DCDCDC')));

*/

        //Condiciones

        if ($dte->ventas->estado == "Anulada") {
            //marca de agua por si es factura anulada
            $pdf->SetAlpha(0.6);
            // Añadir la imagen como marca de agua (centro de la página)
            $pdf->Image($anuladaPath, 30, 50, 150, 150, '', '', '', false, 300, '', false, false, 0);

            // Restablecer la transparencia
            $pdf->SetAlpha(1);
        }

        // Agregar texto dentro del rectángulo en mayúsculas y negrita

        $pdf->SetXY($x + 5, $y);


        //$pdf->Cell($width, 5, 'Total en letras: ' . $totalEnLetras, 0, 1, 'L');
        // $pdf->writeHTML('<p style="margin-left: 40px; text-align: center"> <strong>   Total en letras:</strong> ' . $totalEnLetras . '</p>');
        //$pdf->writeHTML('<p style="margin-left: 40px; text-align: center"> <strong>  Condicion de la operación:</strong> ' . $nombreCondicion . '</p>');
        // Volver a la fuente normal si necesitas más texto después
        //  $pdf->SetFont('Times', '', 10); // Cambia a fuente normal

        // Ajusta la posición Y para la tabla
        // $y += $height + 5;



        return response($pdf->Output('Factura_' . $dte->codigo_generacion . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }
}
