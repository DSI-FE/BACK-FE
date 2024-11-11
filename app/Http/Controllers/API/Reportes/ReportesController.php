<?php

namespace App\Http\Controllers\API\Reportes;


use App\Http\Controllers\Controller;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Ventas\DetalleVenta;
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
        //data
        $ventas = DB::table('dte')
            ->select('dte.*', 'dte.fecha AS dte_fecha', 'ventas.*', 'cliente.nrc', 'cliente.nombres', 'cliente.apellidos')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->join('cliente', 'ventas.cliente_id', '=', 'cliente.id')
            ->whereBetween('dte.fecha', [$fechaInicio, $fechaFin])
            ->where('ventas.estado', 'Finalizada')
            ->where('ventas.tipo_documento', 2)
            ->get();

        /*Este reporte va ser casi identco al anterior, solo que cambiara el tipo de documento, ya so será 1, sino 2 
         y cambiara el estilo del pdf*/
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->setPageOrientation('L');
        $pdf->writeHTML('<h2>Todo el contenido del pdf apartir de aqui</h2>');
        $pdf->writeHTML('<p>DATA: ' . $ventas . '</p>');



        //Retorna el pdf
        return response($pdf->Output('Contribuyente'  . '.pdf', 'S'))
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
        return response($pdf->Output('Inventario'  . '.pdf', 'S'))
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
        return response($pdf->Output('Compras'  . '.pdf', 'S'))
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

    public function ticket($idDTE)
{
    // Obtener el DTE junto con su venta asociada
    $dte = DTE::with('ventas', 'ambiente', 'moneda', 'tipo')->where('id', $idDTE)->first();

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

    // Obtener los datos del emisor
    $emisor = Emisor::with(['department', 'municipality', 'economicActivity'])
        ->where('id', 1)->first();

    // Configuración de TCPDF
    $pdf = new TCPDF();
    $pdf->AddPage('P', [216, 100]); // Formato de ticket
  //  $pdf->setCellMargins(0, 0, 0, 0);
   // $pdf->setPrintHeader(false);
    //$pdf->setPrintFooter(false);
    //$pdf->SetMargins(10, 10, 10); // Márgenes estrechos para el formato de ticket

   // Generar contenido HTML para el ticket
$html = '<div style="text-align: left; font-family: Consolas; font-size: 8px; line-height: 0.8;">';
$html .= '<h5 style="text-align: center; line-height: 1.1">' . $emisor->nombre . '</h5>';
$html .= '<p style="text-align: center;">' . $emisor->economicActivity->actividad . '</p>';
$html .= '<p style="line-height: 0.5; text-align: center;">NRC: ' . $emisor->nrc . '.   NIT: ' . $emisor->nit . '</p>';
$html .= '<p style="line-height: 0.5; text-align: center;">Teléfono: ' . $emisor->telefono . '.   Correo: ' . $emisor->correo . '</p>';
$html .= '<hr>';
$html .= '<p style="text-align: center; font-weight: bold; background-color: #000; color: #fff; margin: 0;">DETALLE DEL DTE</p>';
$html .= '<p style="margin: 0;">Tipo: ' . $dte->tipo->nombre . '</p>';
$html .= '<p style="margin: 0;  line-height: 0.6">Fecha de generación: ' . $dte->fecha . ' ' . $dte->hora . '</p>';
$html .= '<p style="margin: 0;  line-height: 1">Código de generación:<br> ' . $dte->codigo_generacion . '</p>';
$html .= '<p style="margin: 0;  line-height: 1">Número de Control: ' . $dte->numero_control . '</p>';
$html .= '<p style="margin: 0;  line-height: 1">Sello de recepción: ' . $dte->sello_recepcion . '</p>';
$html .= '<hr style="margin: 0;">';
$html .= '<p style="text-align: center; font-weight: bold; background-color: #000; color: #fff; margin: 0;">DATOS DEL CLIENTE</p>';
$html .= '<p style="margin: 0; line-height: 1">Cliente: ' . $dte->ventas->cliente->nombres . ' ' . $dte->ventas->cliente->apellidos . '</p>';
$html .= '<p style="margin: 0;">Dirección: ' . $dte->ventas->cliente->direccion . ', '. $dte->ventas->cliente->municipality_name . ', '.$dte->ventas->cliente->department_name. '</p>';
$html .= '<p style="margin: 0;">Teléfono: ' . $dte->ventas->cliente->telefono . '  '. '  Correo: ' . $dte->ventas->cliente->correoElectronico . '</p>';
$html .= '<hr>';
    $html .= '<p style="text-align: center; font-weight: bold; background-color: #000; color: #fff;">DETALLE DE LA VENTA</p>';

    // Detalles de artículos
    $html .= '<table width="100%" cellpadding="5" style="margin: 0; border-collapse: collapse;">
            <thead>
                <tr style="font-weight: bold; background-color: #ccc;">
                    <th style="width: 20px">N°</th> 
                    <th style="width: 30px">Cant.</th>
                    <th style="width: 97px">Artículo</th>
                    <th style="width: 40px">Precio/u</th>
                    <th style="width: 40px">Total</th>
                </tr>
            </thead>
            <tbody>';
    $contador = 1;
    foreach ($detalle as $item) {
        $html .= '<tr>
                <td style="width: 20px">' . $contador++ . '</td>
                <td style="width: 25px">' . $item->cantidad . '</td>
                <td style="width: 90px">' . $item->producto->nombre_producto . '</td>
                <td style="width: 40px">$' . number_format($item->precio, 2) . '</td>
                <td style="width: 50px">$' . number_format($item->total, 2) . '</td>
              </tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<hr>';
    $html .= '<p style="text-align: right; font-weight: bold;">Total a pagar: $' . number_format($dte->ventas->total_pagar, 2) . '</p>';
    $html .= '<hr><br>';

    $html .= '</div>';

    // Agregar el contenido HTML al PDF usando writeHTML
    $pdf->writeHTML($html, true, false, true, false, '');

     //RUTA DEL QR ESTATICO
     $imageQR = storage_path('app/public/QRCODES/' . $dte->qr_code);
     $y = $pdf->getY();
     $pdf->Image($imageQR, 35, $y, 30, 30, 'PNG', '', '', false, 150, '', false, false, 1, false, false, false);

    // Salida del PDF
    return response($pdf->Output('Ticket.pdf', 'S'))
        ->header('Content-Type', 'application/pdf');
}

}
