<?php

namespace App\Http\Controllers\API\Reportes;


use App\Http\Controllers\Controller;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use TCPDF;

class ReportesController extends Controller
{
    //Funcion para obtener el libro de ventas a consumidor en pdf
    public function consumidor($fechaInicio, $fechaFin)
    {

        //Obtenemos la informacion del emisor
        $emisor = Emisor::first();
        //Obtenemos la informacion de las ventas
        $ventas = DTE::select('dte.fecha as dte_fecha', 'ventas.*')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->whereBetween('dte.fecha', [$fechaInicio, $fechaFin])
            ->where('ventas.estado', 'Finalizada')
            ->where('ventas.tipo_documento', 1)
            ->get();


        // Convertimos la fecha de inicio para obtener el mes y el año
        $fechaInicioCarbon = \Carbon\Carbon::parse($fechaInicio);
        $anio = $fechaInicioCarbon->year; // Obtiene el año de la fecha de inicio

        // Convertimos el número del mes a texto
        $nombreMes = $fechaInicioCarbon->formatLocalized('%B');
        // Poner la primera letra en mayúscula
        $nombreMes = ucfirst($nombreMes);

        //Aqui se crea el pdf y se le agrega el diseño que se necesite
        $pdf = new TCPDF();
        $pdf->AddPage('P', [216, 279]);
        $pdf->writeHTML('<h2>' . $emisor->nombre_comercial . '</h2>, ', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h4>Libro de ventas a consumidor</h4>, ', 0, 0, 0, 0, 'C');
        // Crear una tabla con dos columnas
        $pdf->Cell(70, 5, 'Mes: ' . $nombreMes, 0, 0, 'L'); // Alineado a la izquierda
        $pdf->Cell(95, 5, 'Contribuyente: ' . $emisor->nombre, 0, 1, 'R');
        $pdf->Cell(50, 5, 'Año: ' . $anio, 0, 0, 'L');
        $pdf->Cell(35, 5, 'NRC: ' . $emisor->nrc, 0, 0, 'C');
        $pdf->Cell(90, 5, 'NIT: ' . $emisor->nit, 0, 0, 'R');
        $pdf->Ln();

        $tabla = '
<table  border="0" cellpading="0" cellspacing="0" style="border-collapse: collapse; width: 100%;" >
    <thead>
        <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
            <th rowspan="2" style="border: 1px solid black; width: 27px">N°</th>
            <th rowspan="2" style="border: 1px solid black; width: 70px">Fecha</th>
            <th rowspan="2" style="border: 1px solid black">Del No.</th>
            <th rowspan="2" style="border: 1px solid black">Al No.</th>
            <th colspan="3" style="border: 1px solid black; align: center; width: 193px">Ventas</th>
            <th rowspan="2" style="border: 1px solid black; font-size: 10px">Ventas por terceros</th>
            <th rowspan="2" style="border: 1px solid black; width: 73px">Total</th>
        </tr>
        <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
            <th style="border: 1px solid black; text-align: center; width: 75px">Gravadas</th>
            <th style="border: 1px solid black; text-align: center; width: 68px">Exentas</th>
            <th style="border: 1px solid black; text-align: center; width: 50px">No sujetas</th>
        </tr>
    </thead>
    <tbody>';
        //contador
        $numero = 1;
        //variables para guardar las sumas
        $gravadas = 0;
        $exentas = 0;
        // Iterar para mostrar cada venta en la tabla
        foreach ($ventas as $item) {
            $gravadas += $item['total_pagar'];
            $exentas += $item['total_exentas'];
            $tabla .= '<tr style="font-size: 12px; border: 1px dotted gray; font-size: 11px;">
        <td style="border: 1px dotted gray; width: 27px; height: 15px; vertical-align: bottom;">' . $numero++ . '</td>
        <td style="border: 1px dotted gray; width: 70px">' . $item['dte_fecha'] . '</td>
        <td style="border: 1px dotted gray">' . $item[''] . '</td>
        <td style="border: 1px dotted gray">' . $item[''] . '</td>
        <td style="border: 1px dotted gray; width: 75px">$ ' . $item['total_pagar'] . '</td>
        <td style="border: 1px dotted gray; width: 68px">$ ' . $item['total_exentas'] . '</td>
        <td style="border: 1px dotted gray; width: 50px">$ 0.00</td>
        <td style="border: 1px dotted gray">$ 0.00</td>
        <td style="border: 1px dotted gray; width: 73px">$ ' . $item['total_pagar'] . '</td>
        </tr>';
        }
        $tabla .= '
        <hr>
        <tr style="font-weight: bold">
            <td colspan="4" style="text-align: right; text-align: center">SUMAS</td>
            <td style="width: 75px">$ ' . number_format($gravadas, 2) . '</td>
            <td style="width: 70px">$ ' . number_format($exentas, 2) . '</td>
            <td>$ 0.00</td>
            <td>$ 0.00</td>
            <td>$ ' . number_format($gravadas, 2) . '</td>
        </tr>
        </tbody>
        </table>
        <hr>';
        // Ahora escribimos la tabla en el PDF
        $pdf->writeHTML($tabla, true, false, true, false, '');


        //Resumen del libro
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(60, 5, 'Ventas Netas Gravadas:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(30, 5, '$ ' . number_format($gravadas / 1.13, 2), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 12);

        $pdf->Cell(60, 5, 'Ventas Exentas:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(30, 5, '$ ' . number_format($exentas, 2), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 12);
        //Espacio para firma del contador
        $pdf->Cell(90, 5, '___________________________________', 0, 1, 'R');

        $pdf->Cell(60, 5, 'IVA Débito Fiscal:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(30, 5, '$ ' . number_format(($gravadas / 1.13) * 0.13, 2), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 12);
        //Nombre del contador
        $pdf->Cell(75, 5, $emisor->contador, 0, 1, 'R');

        $pdf->Cell(60, 5, 'Total:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(30, 5, '$ ' . number_format($gravadas, 2), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 12);
        //Rol del que firma
        $pdf->Cell(60, 5, $emisor->rol_contador, 0, 1, 'R');
        $pdf->Ln();

        //Retornamos el pdf
        return response($pdf->Output('Consumidor -' . $nombreMes . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }


    //Funcion para obtener el libro de ventas a contribuyente en pdf
    public function contribuyente($fechaInicio, $fechaFin)
    {
        // Obtener la información del emisor
        $emisor = Emisor::first();
        //data
        $ventas = DB::table('dte')
            ->select('dte.*', 'dte.fecha AS dte_fecha', 'ventas.*', 'cliente.nrc', 'cliente.nombres', 'cliente.apellidos')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->join('cliente', 'ventas.cliente_id', '=', 'cliente.id')
            ->whereBetween('dte.fecha', [$fechaInicio, $fechaFin])
            ->whereIn('ventas.estado', ['Finalizada', 'Anulada'])
            ->where('ventas.tipo_documento', 2)
            ->get();

        // Convertir la fecha de inicio para obtener mes y año
        $fechaInicioCarbon = \Carbon\Carbon::parse($fechaInicio);
        $anio = $fechaInicioCarbon->year;
        $nombreMes = ucfirst($fechaInicioCarbon->translatedFormat('F'));

        // Inicializar el PDF
        $pdf = new TCPDF();
        $pdf->AddPage('L', 'A4');
        $pdf->SetFont('Helvetica', '', 12);

        // Título y encabezado del PDF
        $pdf->writeHTML('<h2>' . $emisor->nombre_comercial . '</h2>, ', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h4>Libro de ventas a contribuyente</h4>, ', 0, 0, 0, 0, 'C');
        $pdf->Ln(5);

        // Encabezados adicionales
        $pdf->Cell(70, 5, 'Mes: ' . $nombreMes, 0, 0, 'L');
        $pdf->Cell(200, 5, 'Contribuyente: ' . $emisor->nombre, 0, 1, 'R');
        $pdf->Cell(50, 5, 'Año: ' . $anio, 0, 0, 'L');
        $pdf->Cell(145, 5, 'NRC: ' . $emisor->nrc, 0, 0, 'C');
        $pdf->Cell(73, 5, 'NIT: ' . $emisor->nit, 0, 1, 'R');
        $pdf->Ln(5);

        // Definir estructura de la tabla
        $tabla = '
    <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
                <th rowspan="2" style="border: 1px solid black; width: 35px">Corr.</th>
                <th rowspan="2" style="border: 1px solid black; width: 65px">Fecha</th>
                <th rowspan="2" style="border: 1px solid black; width: 130px">No. de CCF</th>
                <th rowspan="2" style="border: 1px solid black; width: 60px">N.R.C</th>
                <th rowspan="2" style="border: 1px solid black; width: 160px">Cliente</th>
                <th colspan="3" style="border: 1px solid black; align: center; width: 200px">Ventas</th>
                <th rowspan="2" style="border: 1px solid black; width: 65px">Impuesto Percibido</th>
                <th rowspan="2" style="border: 1px solid black; width: 70px">Total</th>
            </tr>
            <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
            <th style="border: 1px solid black; text-align: center; width: 60px">Exentas</th>
            <th style="border: 1px solid black; text-align: center; width: 70px">Internas Gravadas</th>
            <th style="border: 1px solid black; text-align: center; width: 70px">Debito Fiscal</th>
        </tr>
        </thead>
        <tbody>';

        // Inicializar acumuladores para los totales
        $totalExentas = 0;
        $totalGravadas = 0;
        $totalDebitoFiscal = 0;
        $totalImpuestoPercibido = 0;
        $totalGeneral = 0;

        // Generar filas de la tabla con datos
        /*foreach ($ventas as $index => $venta) {
            $totalExentas += $venta->total_exentas;
            $totalGravadas += $venta->total_gravadas;
            $totalDebitoFiscal += $venta->total_iva;
            $totalImpuestoPercibido += 0.00;  // Columna de IMPUESTO PERCIBIDO es 0.00
            $totalGeneral += $venta->total_pagar;*/

             // Generar filas de la tabla con datos
    foreach ($ventas as $index => $venta) {
        $isAnulada = $venta->estado === 'Anulada';

        // Determinar valores según el estado de la venta
        $exentas = $isAnulada ? '$0.00' : '$ ' . number_format($venta->total_exentas, 2);
        $gravadas = $isAnulada ? '$0.00' : '$ ' . number_format($venta->total_gravadas, 2);
        $debitoFiscal = $isAnulada ? '$0.00' : '$ ' . number_format($venta->total_iva, 2);
        $impuestoPercibido = '$0.00';
        $total = $isAnulada ? '$0.00' : '$ ' . number_format($venta->total_pagar, 2);
        $cliente = $venta->nombres . ' ' . $venta->apellidos;
        
        if ($isAnulada) {
            $cliente .= ' **ANULADA**';
        } else {
            // Acumular solo si la venta no está anulada
            $totalExentas += $venta->total_exentas;
            $totalGravadas += $venta->total_gravadas;
            $totalDebitoFiscal += $venta->total_iva;
            $totalImpuestoPercibido += 0.00;  // Impuesto Percibido es 0.00
            $totalGeneral += $venta->total_pagar;
        }

        $tabla .= '<tr style="font-size: 12px; border: 1px dotted gray; font-size: 11px;">
        <td style="border: 1px dotted gray; width: 35px; height: 15px; text-align: center; vertical-align: bottom;">' . ($index + 1) . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 65px">' . $venta->dte_fecha . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 130px">' . $venta->codigo_generacion . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 60px">' . ($venta->nrc ?? 'N/A') . '</td>
        <td style="border: 1px dotted gray; text-align: left; width: 160px">' . $cliente . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 60px">' . $exentas . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 70px">' . $gravadas . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 70px">' . $debitoFiscal . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 65px">' . $impuestoPercibido . '</td>
        <td style="border: 1px dotted gray; text-align: center; width: 70px">' . $total . '</td>
    </tr>';
        }

        // Agregar fila de totales
        $tabla .= '<tr style="font-weight: bold; text-align: right;">
    <td colspan="5" style="border: 1px solid black; text-align: center;">TOTALES</td>
    <td style="border: 1px solid black; text-align: center;">$ ' . number_format($totalExentas, 2) . '</td>
    <td style="border: 1px solid black; text-align: center;">$ ' . number_format($totalGravadas, 2) . '</td>
    <td style="border: 1px solid black; text-align: center;">$ ' . number_format($totalDebitoFiscal, 2) . '</td>
    <td style="border: 1px solid black; text-align: center;">$ ' . number_format($totalImpuestoPercibido, 2) . '</td>
    <td style="border: 1px solid black; text-align: center;">$ ' . number_format($totalGeneral, 2) . '</td>
</tr>';

        $tabla .= '</tbody></table>';

        // Escribir la tabla en el PDF
        $pdf->writeHTML($tabla, true, false, true, false, '');

        // Agregar un espacio vertical adicional entre la tabla y la firma
        $pdf->Ln(20);
        //Resumen del libro
        $pdf->SetFont('Helvetica', '', 12);
        //Espacio para firma del contador
        $pdf->Cell(160);
        $pdf->Cell(90, 5, '___________________________________', 0, 1, 'R');
        //Nombre del contador
        $pdf->Cell(160);
        $pdf->Cell(75, 5, $emisor->contador, 0, 1, 'R');
        //Rol del que firma
        $pdf->Cell(160);
        $pdf->Cell(60, 5, $emisor->rol_contador, 0, 1, 'R');
        $pdf->Ln();

        // Retornar el PDF
        return response($pdf->Output('Contribuyente - ' . $nombreMes . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }


    //Funcion para obtener el reporte de inventario en pdf
    public function inventario()
    {
        //data
        $productos = DB::table('productos')
            ->join('inventario', 'inventario.producto_id', '=', 'productos.id')
            ->join('unidadmedida', 'inventario.unidad_medida_id', '=', 'unidadmedida.id')
            ->select('inventario.*', 'productos.*', 'productos.id as cod_prod', 'unidadmedida.nombreUnidad')
            ->where('inventario.equivalencia', 1)
            ->get();


        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->writeHTML('<h2>NombreEmpresa</h2>', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h2>Reporte de inventario</h2>', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<p>LLevara 1 tabla con 7 columnas</p>');
        $pdf->writeHTML('<p>N°, Codigo del producto, Nombre del producto, Unidad de medida, Existencias, Precio de Costo, Precio de Venta.</p>');
        $pdf->writeHTML('<p>Y al final un campo total que sume el precio de costo de todos los productos</p>');
        $pdf->writeHTML('<p>DATA' . $productos . '</p>');


        //Retorna el pdf
        return response($pdf->Output('Inventario' . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }

    public function compras($mes, $anio)
    {

        $compras = DB::table('compras')
            ->join('proveedor', 'proveedor.id', '=', 'compras.proveedor_id')
            ->join('tipo_proveedor', 'tipo_proveedor.id', '=', 'proveedor.id')
            ->select('compras.*', 'proveedor.*', 'tipo_proveedor.tipo')
            ->whereYear('compras.fecha', $anio)
            ->whereMonth('compras.fecha', $mes)
            ->get();

        //Obtenemos las compras del mes

        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->setPageOrientation('L');
        $pdf->writeHTML('<h2>NombreEmpresa</h2>', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h2>Libro de compras</h2>', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<p>DATA: ' . $compras . '</p>');


        //Retorna el pdf
        return response($pdf->Output('Compras' . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }


    //Retorna un excel con ventas a consumidor
    public function ConsumidorExcel($fechaInicio, $fechaFin)
    {
        // Encabezados del Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'N°');
        $sheet->setCellValue('B1', 'Fecha');
        $sheet->setCellValue('C1', 'No. Resolucion');
        $sheet->setCellValue('D1', 'Serie');
        $sheet->setCellValue('E1', 'Del No.');
        $sheet->setCellValue('F1', 'Al No.');
        $sheet->setCellValue('G1', 'Ventas Exentas');
        $sheet->setCellValue('H1', 'Ventas Gravadas');
        $sheet->setCellValue('I1', 'Total de Venta');

        // Establecer el ancho de las columnas
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(40);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);

        // Estilo de la cabecera
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            // Color gris claro
            ->getStartColor()->setARGB('FFD3D3D3');

        // Información de las ventas
        $ventas = DTE::select('dte.*', 'dte.fecha as dte_fecha', 'ventas.*')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->whereBetween('dte.fecha', [$fechaInicio, $fechaFin])
            ->where('ventas.estado', 'Finalizada')
            ->where('ventas.tipo_documento', 1)
            ->get();

        // Desplegando la información en el Excel a partir de la fila 2
        $row = 2;
        $contador = 1;
        foreach ($ventas as $item) {
            $sheet->setCellValue('A' . $row, $contador++);
            $sheet->setCellValue('B' . $row, $item['dte_fecha']);
            $sheet->setCellValue('C' . $row, $item['numero_control']);
            $sheet->setCellValue('D' . $row, $item['sello_recepcion']);
            $sheet->setCellValue('E' . $row, $item['codigo_generacion']);
            $sheet->setCellValue('F' . $row, $item['codigo_generacion']);
            $sheet->setCellValue('G' . $row, number_format($item['total_exentas'], 2));
            $sheet->setCellValue('H' . $row, number_format($item['total_pagar'], 2));
            $sheet->setCellValue('I' . $row, number_format($item['total_pagar'], 2));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_end_clean();

        // Enviar las cabeceras adecuadas para la descarga
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Consumidor.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function ContribuyenteExcel($fechaInicio, $fechaFin)
    {
        // Encabezados del Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'N°');
        $sheet->setCellValue('B1', 'Fecha');
        $sheet->setCellValue('C1', 'Serie del documento');
        $sheet->setCellValue('D1', 'Numero de documento');
        $sheet->setCellValue('E1', 'NRC');
        $sheet->setCellValue('F1', 'Nombre');
        $sheet->setCellValue('G1', 'Ventas Exentas');
        $sheet->setCellValue('H1', 'Ventas Gravadas');
        $sheet->setCellValue('I1', 'IVA Débito Fiscal');
        $sheet->setCellValue('J1', 'Total de Venta');

        // Establecer el ancho de las columnas
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(35);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);
        $sheet->getColumnDimension('J')->setWidth(15);

        // Estilo de la cabecera
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            // Color gris claro
            ->getStartColor()->setARGB('FFD3D3D3');

        $ventas = DB::table('dte')
            ->select('dte.*', 'dte.fecha AS dte_fecha', 'ventas.*', 'cliente.nrc', 'cliente.nombres', 'cliente.apellidos')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->join('cliente', 'ventas.cliente_id', '=', 'cliente.id')
            ->whereBetween('dte.fecha', [$fechaInicio, $fechaFin])
            ->where('ventas.estado', 'Finalizada')
            ->where('ventas.tipo_documento', 2)
            ->get();

        // Desplegando la información en el Excel a partir de la fila 2
        $row = 2;
        $contador = 1;
        foreach ($ventas as $item) {
            $sheet->setCellValue('A' . $row, $contador++);
            $sheet->setCellValue('B' . $row, $item->dte_fecha);
            $sheet->setCellValue('C' . $row, $item->sello_recepcion);
            $sheet->setCellValue('D' . $row, $item->codigo_generacion);
            $sheet->setCellValue('E' . $row, $item->nrc);
            $sheet->setCellValue('F' . $row, $item->nombres . ' ' . $item->apellidos);
            $sheet->setCellValue('G' . $row, number_format($item->total_exentas, 2));
            $sheet->setCellValue('H' . $row, number_format($item->total_pagar / 1.13, 2));
            $sheet->setCellValue('I' . $row, number_format(($item->total_pagar / 1.13) * 0.13, 2));
            $sheet->setCellValue('J' . $row, number_format($item->total_pagar, 2));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_end_clean();

        // Enviar las cabeceras adecuadas para la descarga
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Contribuyente.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    //Reporte de excel de inventario
    public function inventarioExcel()
    {
        // Encabezados del Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'N°');
        $sheet->setCellValue('B1', 'Codigo del producto');
        $sheet->setCellValue('C1', 'Nombre del producto');
        $sheet->setCellValue('D1', 'Unidad de medida');
        $sheet->setCellValue('E1', 'Existencias');
        $sheet->setCellValue('F1', 'Precio de Costo');
        $sheet->setCellValue('G1', 'Total');

        // Establecer el ancho de las columnas
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);

        // Estilo de la cabecera
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            // Color gris claro
            ->getStartColor()->setARGB('FFD3D3D3');

        // Información del inventario
        $productos = DB::table('productos')
            ->join('inventario', 'inventario.producto_id', '=', 'productos.id')
            ->join('unidadmedida', 'inventario.unidad_medida_id', '=', 'unidadmedida.id')
            ->select('inventario.*', 'productos.*', 'productos.id as cod_prod', 'unidadmedida.nombreUnidad')
            ->where('inventario.equivalencia', 1)
            ->get();


        // Desplegando la información en el Excel a partir de la fila 2
        $row = 2;
        $contador = 1;
        foreach ($productos as $item) {
            $sheet->setCellValue('A' . $row, $contador++);
            $sheet->setCellValue('B' . $row, $item->cod_prod);
            $sheet->setCellValue('C' . $row, $item->nombreProducto);
            $sheet->setCellValue('D' . $row, $item->nombreUnidad);
            $sheet->setCellValue('E' . $row, $item->existencias);
            $sheet->setCellValue('F' . $row, $item->precioCosto);
            $sheet->setCellValue('G' . $row, $item->precioCosto * $item->existencias);
            $row++;
        }

        // Agregar fila para el total del inventario en negrita
        $sheet->setCellValue('C' . ($row + 2), 'TOTAL INVENTARIO');
        $sheet->setCellValue('G' . ($row + 2), '=SUM(G2:G' . ($row - 1) . ')');

        // Aplicar estilo en negrita a las celdas de "TOTAL INVENTARIO" y su total
        $sheet->getStyle('C' . ($row + 2))->getFont()->setBold(true);
        $sheet->getStyle('G' . ($row + 2))->getFont()->setBold(true);


        $writer = new Xlsx($spreadsheet);
        ob_end_clean();

        // Enviar las cabeceras adecuadas para la descarga
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Inventario.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    //Funcion para descargar excel para las compras
    public function comprasExcel($mes, $anio)
    {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'N°');
        $sheet->setCellValue('B1', 'Fecha');
        $sheet->setCellValue('C1', 'Clase');
        $sheet->setCellValue('D1', 'Numero de CCF');
        $sheet->setCellValue('E1', 'NRC');
        $sheet->setCellValue('F1', 'Nombre');
        $sheet->setCellValue('G1', 'Compras Exentas');
        $sheet->setCellValue('H1', 'Compras Gravadas');
        $sheet->setCellValue('I1', 'IVA Crébito Fiscal');
        $sheet->setCellValue('J1', 'Total de Compra');

        // Establecer el ancho de las columnas
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(35);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);
        $sheet->getColumnDimension('J')->setWidth(15);

        // Estilo de la cabecera
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            // Color gris claro
            ->getStartColor()->setARGB('FFD3D3D3');

        $compras = DB::table('compras')
            ->join('proveedor', 'proveedor.id', '=', 'compras.proveedor_id')
            ->join('tipo_proveedor', 'tipo_proveedor.id', '=', 'proveedor.id')
            ->select('compras.*', 'proveedor.*', 'tipo_proveedor.tipo')
            ->whereYear('compras.fecha', $anio)
            ->whereMonth('compras.fecha', $mes)
            ->get();

        // Desplegando la información en el Excel a partir de la fila 2
        $row = 2;
        $contador = 1;
        foreach ($compras as $item) {
            $sheet->setCellValue('A' . $row, $contador++);
            $sheet->setCellValue('B' . $row, $item->fecha);
            $sheet->setCellValue('C' . $row, $item->tipo);
            $sheet->setCellValue('D' . $row, $item->numeroCCF);
            $sheet->setCellValue('E' . $row, $item->nrc);
            $sheet->setCellValue('F' . $row, $item->nombre);
            $sheet->setCellValue('G' . $row, number_format($item->comprasExentas, 2));
            $sheet->setCellValue('H' . $row, number_format($item->comprasGravadas, 2));
            $sheet->setCellValue('I' . $row, number_format(($item->ivaCompra), 2));
            $sheet->setCellValue('J' . $row, number_format($item->totalCompra, 2));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_end_clean();

        // Enviar las cabeceras adecuadas para la descarga
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Compras-' . $mes . '-' . $anio . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
