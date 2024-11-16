<?php

namespace App\Http\Controllers\API\Reportes;


use App\Http\Controllers\Controller;
use App\Models\DTE\DTE;
use App\Models\DTE\Emisor;
use App\Models\Ventas\DetalleVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use TCPDF;

class ReportesController extends Controller
{
    //Funcion para obtener el libro de ventas a consumidor en pdf
    public function consumidor($fecha)
    {
        [$anio, $mes] = explode('-', $fecha);

        //Obtenemos la informacion del emisor
        $emisor = Emisor::first();
        //Obtenemos la informacion de las ventas
        $ventas = DTE::select('dte.fecha as dte_fecha', 'dte.codigo_generacion as codigo_generacion', 'ventas.*')
            ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
            ->whereYear('dte.fecha', $anio)
            ->whereMonth('dte.fecha', $mes)
            ->whereIn('ventas.estado', ['Finalizada', 'Anulada'])
            ->where('ventas.tipo_documento', 1)
            ->get();


        // Convertimos la fecha de inicio para obtener el mes y el año
        $fechaI = \Carbon\Carbon::parse($fecha);
        $nombreMes = ucfirst($fechaI->translatedFormat('F'));

        // Obtener el año en formato texto
        $anio = $fechaI->year;

        //Aqui se crea el pdf y se le agrega el diseño que se necesite
        $pdf = new TCPDF();
        $pdf->AddPage('P', [216, 279]);
        $pdf->setMargins(15, 15, 15);
        $pdf->SetFont('helvetica', '', 9); // Tipo de fuente, estilo (normal), tamaño

        // Ahora escribes el texto con el tamaño de fuente configurado
        $pdf->writeHTML('<h3>' . $emisor->nombre . '</h3>, ', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h4>Libro de ventas a consumidor</h4>, ', 0, 0, 0, 0, 'C');

        // Resto del código
        $pdf->Cell(70, 5, 'Mes: ' . $nombreMes, 0, 0, 'L');
        $pdf->Cell(95, 5, 'Contribuyente: ' . $emisor->nombre_comercial, 0, 1, 'L');
        $pdf->Cell(50, 5, 'Año: ' . $anio, 0, 0, 'L');
        $pdf->Cell(35, 5, 'NRC: ' . $emisor->nrc, 0, 0, 'C');
        $pdf->Cell(90, 5, 'NIT: ' . $emisor->nit, 0, 0, 'R');
        $pdf->Ln();
        $tabla = '
<table  border="0" cellpading="0" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9px" >
    <thead>
        <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
            <th rowspan="2" style="border: 1px solid black; width: 27px">N°</th>
            <th rowspan="2" style="border: 1px solid black; width: 65px">Fecha</th>
            <th rowspan="2" style="border: 1px solid black; width: 110px">No. Fact.</th>
         
            <th colspan="3" style="border: 1px solid black; align: center; width: 170px">Ventas</th>
            <th rowspan="2" style="border: 1px solid black; font-size: 9px">Ventas por terceros</th>
            <th rowspan="2" style="border: 1px solid black; width: 73px">Total</th>
        </tr>
        <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
            <th style="border: 1px solid black; text-align: center; width: 60px">Gravadas</th>
            <th style="border: 1px solid black; text-align: center; width: 55px">Exentas</th>
            <th style="border: 1px solid black; text-align: center; width: 55px">No sujetas</th>
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
            // Si el estado es "anulada", establece total_pagar a 0
            $totalPagar = $item['estado'] === 'Anulada' ? 0 : $item['total_pagar'];
            $gravadas += $totalPagar;
            $exentas += $item['total_exentas'];

            //Evaluar si la venta es anulada
            $estado = $item['estado'];
            $codigoGen = $item['codigo_generacion'];
            if ($estado === 'Anulada') {
                $codigoGeneracion = $codigoGen . ' <strong>**ANULADA**</strong>';
                $totalVenta = 0;
            } else {
                $totalVenta = $item['total_pagar'];
                $codigoGeneracion = $codigoGen;
            }
            $tabla .= '<tr style="font-size: 8.5px; border: 1px dotted gray; font-size: 8.5px;">
        <td style="border: 1px dotted gray; width: 27px; height: 15px; vertical-align: bottom;">' . $numero++ . '</td>
        <td style="border: 1px dotted gray; width: 65px">' . $item['dte_fecha'] . '</td>
        <td style="border: 1px dotted gray; width: 110px">' . $codigoGeneracion . '</td>
        <td style="border: 1px dotted gray; width: 60px">$ ' . number_format($totalVenta, 2) . '</td>
        <td style="border: 1px dotted gray; width: 55px">$ ' . $item['total_exentas'] . '</td>
        <td style="border: 1px dotted gray; width: 55px">$ 0.00</td>
        <td style="border: 1px dotted gray">$ 0.00</td>
        <td style="border: 1px dotted gray; width: 73px">$ ' . number_format($totalVenta, 2) . '</td>
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
     public function contribuyente($fecha)
     {
            [$anio, $mes] = explode('-', $fecha);

         // Obtener la información del emisor
         $emisor = Emisor::first();
         //data
         $ventas = DB::table('dte')
             ->select('dte.*', 'dte.fecha AS dte_fecha', 'ventas.*', 'cliente.nrc', 'cliente.nombres', 'cliente.apellidos')
             ->join('ventas', 'dte.id_venta', '=', 'ventas.id')
             ->join('cliente', 'ventas.cliente_id', '=', 'cliente.id')
             ->whereYear('dte.fecha', $anio)
             ->whereMonth('dte.fecha', $mes)
             ->whereIn('ventas.estado', ['Finalizada', 'Anulada'])
             ->where('ventas.tipo_documento', 2)
             ->get();
 
            // Convertir la fecha de inicio para obtener el mes y el año
            $fechaI = \Carbon\Carbon::parse($fecha);
            $nombreMes = ucfirst($fechaI->translatedFormat('F'));

            // Obtener el año en formato texto
            $anio = $fechaI->year;

         // Inicializar el PDF
         $pdf = new TCPDF();
         $pdf->AddPage('L', 'A4');
         $pdf->SetFont('Helvetica', '', 12);
         $pdf->setMargins(15, 15, 15);
         $pdf->SetFont('helvetica', '', 9); // Tipo de fuente, estilo (normal), tamaño
 
         // Título y encabezado del PDF
         $pdf->writeHTML('<h2>' . $emisor->nombre . '</h2>, ', 0, 0, 0, 0, 'C');
         $pdf->writeHTML('<h4>Libro de ventas a contribuyente</h4>, ', 0, 0, 0, 0, 'C');
         $pdf->Ln(5);
 
         // Encabezados adicionales
         $pdf->Cell(70, 5, 'Mes: ' . $nombreMes, 0, 0, 'L');
         $pdf->Cell(180, 5, 'Contribuyente: ' . $emisor->nombre_comercial, 0, 1, 'R');
         $pdf->Cell(50, 5, 'Año: ' . $anio, 0, 0, 'L');
         $pdf->Cell(145, 5, 'NRC: ' . $emisor->nrc, 0, 0, 'C');
         $pdf->Cell(62, 5, 'NIT: ' . $emisor->nit, 0, 1, 'R');
         $pdf->Ln(5);
 
         // Definir estructura de la tabla
         $tabla = '
     <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 8.5px">
         <thead>
             <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
                 <th rowspan="2" style="border: 1px solid black; width: 25px">Corr.</th>
                 <th rowspan="2" style="border: 1px solid black; width: 55px">Fecha</th>
                 <th rowspan="2" style="border: 1px solid black; width: 130px">No. de CCF</th>
                 <th rowspan="2" style="border: 1px solid black; width: 55px">N.R.C</th>
                 <th rowspan="2" style="border: 1px solid black; width: 160px">Cliente</th>
                 <th colspan="3" style="border: 1px solid black; align: center; width: 180px">Ventas</th>
                 <th rowspan="2" style="border: 1px solid black; width: 55px">Impuesto Percibido</th>
                 <th rowspan="2" style="border: 1px solid black; width: 65px">Total</th>
             </tr>
             <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
             <th style="border: 1px solid black; text-align: center; width: 50px">Exentas</th>
             <th style="border: 1px solid black; text-align: center; width: 65px">Internas Gravadas</th>
             <th style="border: 1px solid black; text-align: center; width: 65px">Debito Fiscal</th>
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
             $cliente .= '<strong> **ANULADA**</strong>';
         } else {
             // Acumular solo si la venta no está anulada
             $totalExentas += $venta->total_exentas;
             $totalGravadas += $venta->total_gravadas;
             $totalDebitoFiscal += $venta->total_iva;
             $totalImpuestoPercibido += 0.00;  // Impuesto Percibido es 0.00
             $totalGeneral += $venta->total_pagar;
         }
 
         $tabla .= '<tr style="font-size: 8.5px; border: 1px dotted gray;">
         <td style="border: 1px dotted gray; width: 25px; height: 15px; text-align: center; vertical-align: bottom;">' . ($index + 1) . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 55px">' . $venta->dte_fecha . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 130px">' . $venta->codigo_generacion . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 55px">' . ($venta->nrc ?? 'N/A') . '</td>
         <td style="border: 1px dotted gray; text-align: left; width: 160px">' . $cliente . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 50px">' . $exentas . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 65px">' . $gravadas . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 65px">' . $debitoFiscal . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 55px">' . $impuestoPercibido . '</td>
         <td style="border: 1px dotted gray; text-align: center; width: 65px">' . $total . '</td>
     </tr>';
         }
 
         // Agregar fila de totales
         $tabla .= '<tr style="font-weight: bold; text-align: right; font-size: 8.5px">
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
        // Obtenemos los productos
        $productos = DB::table('productos')
            ->join('inventario', 'inventario.producto_id', '=', 'productos.id')
            ->join('unidadmedida', 'inventario.unidad_medida_id', '=', 'unidadmedida.id')
            ->select('inventario.*', 'productos.*', 'productos.id as cod_prod', 'unidadmedida.nombreUnidad')
            ->where('inventario.equivalencia', 1)
            ->get();

        $emisor = Emisor::first();

        $pdf = new TCPDF();

        $pdf->SetPrintHeader(false); 
        $pdf->SetPrintFooter(false); 
        $pdf->AddPage('P', [216, 279]);
        $pdf->setMargins(15, 15, 15);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->writeHTML('<h2>' . $emisor->nombre . '</h2>', true, 0, 0, 0, 'C');
        $pdf->writeHTML('<h2>Reporte de inventario</h2><br>', true, 0, 0, 0, 'C');

        // Datos del emisor
        $pdf->Cell(70, 5, 'Contribuyente: ' . $emisor->nombre_comercial, 0, 1, 'L');
        $pdf->Cell(60, 5, 'NIT: ' . $emisor->nit, 0, 0, 'L');
        $pdf->Cell(70, 5, 'NRC: ' . $emisor->nrc, 0, 1, 'R');
        $pdf->Ln();

        $tabla = '
        <table  border="0" cellpading="0" cellspacing="0" style="border-collapse: collapse; width: 100%; height:100%;" >
            <thead>
                <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef; font-size: 8.5px">
                    <th rowspan="2" style="border: 1px solid black; width: 27px;height:30px;">N°</th>
                    <th rowspan="2" style="border: 1px solid black; width: 50px">Código producto</th>
                    <th rowspan="2" style="border: 1px solid black; width: 135px">Nombre producto</th>
                    <th rowspan="2" style="border: 1px solid black; width: 65px">Unidad de medida</th>

                    <th style="border-top: 1px solid black; border-left: 1px solid black; border-right: 1px solid black; text-align: center; 
                    width: 65px;">Existencias</th>

                    <th rowspan="2" style="border: 1px solid black; width: 55px">Precio de costo</th>
                    <th rowspan="2" style="border: 1px solid black; width: 55px">Precio de venta</th>
                    <th rowspan="2" style="border: 1px solid black; width: 65px">Total</th>

                </tr>
                <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
                    <th style="border-bottom: 1px solid black; text-align: center; width: 75px;"></th>
                </tr>
            </thead>
            <tbody>';
        //contador
        $numero = 1;
        //variables para guardar las sumas
        $sumaPrecioCosto = 0;
        $sumaPrecioVenta = 0;
        $sumaTotal = 0;

        // Iterar para mostrar cada venta en la tabla
        foreach ($productos as $item) {
            $total = $item->existencias * $item->precioCosto;

            $sumaPrecioCosto += $item->precioCosto;
            $sumaPrecioVenta += $item->precioVenta;
            $sumaTotal += $total;

            $tabla .= '
            <tr style="font-size: 12px; border: 1px dotted gray; font-size: 8.5px;">
                <td style="border: 1px dotted gray; width: 27px; height: 15px; vertical-align: bottom;">' . $numero++ . '</td>
                <td style="border: 1px dotted gray; width: 50px; text-align: center">' . $item->cod_prod. '</td>
                <td style="border: 1px dotted gray; width: 135px">' . $item->nombreProducto . '</td>
                <td style="border: 1px dotted gray; width: 65px; text-align: center">' .$item->nombreUnidad . '</td>
                <td style="border: 1px dotted gray; width: 65px; text-align: center;"> ' . number_format($item->existencias, 2) . '</td>
                <td style="border: 1px dotted gray; width: 55px">$ ' . $item->precioCosto . '</td>
                <td style="border: 1px dotted gray; width: 55px">$' . $item->precioVenta . '</td>
                <td style="border: 1px dotted gray; width: 65px">$ ' . number_format($total, 2) . '</td>
            </tr>';
        }
        $tabla .= '
        <hr>
        <tr style="font-weight: bold">
            <td colspan="4" style="text-align: center; width: 342px; border-top: 1px solid black; border-bottom: 1px solid black;">SUMAS</td>
            <td style="width: 55px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black;"></td>
            <td style="width: 55px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black;"></td>
            <td style="width: 65px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black;">$ ' . number_format($sumaTotal, 2) . '</td>
        </tr>
        </tbody>
        </table>
        <hr>';

        // Ahora escribimos la tabla en el PDF
        $pdf->writeHTML($tabla, true, false, true, false, '');

        //Espacio para firma del contador
        $pdf->Ln(15);

        $pdf->Cell(90, 5, '___________________________________', 0, 1, 'R');

        //Nombre del contador
        $pdf->Cell(75, 5, $emisor->contador, 0, 1, 'R');
        $pdf->Cell(60, 5, $emisor->rol_contador, 0, 1, 'R');

        // Retornamos el PDF
        return response($pdf->Output('Inventario.pdf', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="ReporteInventario.pdf"');
    }

    public function compras($fecha)
    {
        [$anio, $mes] = explode('-', $fecha);

        $compras = DB::table('compras')
        ->join('proveedor', 'proveedor.id', '=', 'compras.proveedor_id')
        ->join('tipo_proveedor', 'tipo_proveedor.id', '=', 'proveedor.id')
        ->select('compras.*', 'proveedor.*', 'tipo_proveedor.tipo')
        ->whereYear('compras.fecha', $anio) 
        ->whereMonth('compras.fecha', $mes)
        ->get();

       
        //Obtenemos el nombre del mes y año
        $fechaI = \Carbon\Carbon::parse($fecha);
        $mes = $fechaI->month;
        $anio = $fechaI->year;

        $pdf = new TCPDF();

        $emisor = Emisor::first();

        $pdf->AddPage('L', [216, 279]);
        $pdf->setPageOrientation('L');
        $pdf->setMargins(10, 15, 15);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->writeHTML('<h2>' . $emisor->nombre . '</h2>, ', 0, 0, 0, 0, 'C');
        $pdf->writeHTML('<h2>Libro de compras</h2> <br>', 0, 0, 0, 0, 'C');

        // Crear una tabla con dos columnas
        $pdf->Cell(70, 5, 'Contribuyente: '. $emisor->nombre_comercial, 0, 0, 'L'); // Alineado a la izquierda
        $pdf->Ln();
        $pdf->Cell(60, 5, 'NIT: ' . $emisor->nit, 0, 0, 'L');
        $pdf->Cell(70, 5, 'MES: ' . $mes ?? 'N.A Mes inválido', 0, 0, 'C');
        $pdf->Cell(60, 5, 'AÑO: ' . $anio, 0, 0, 'C');
        $pdf->Cell(70, 5, 'NRC: ' . $emisor->nrc, 0, 0, 'R');
        $pdf->Ln();
        $pdf->Ln();

        //$pdf->writeHTML('<p>DATA: '.$compras.'</p>');
        
        //Imprimir tabla
        $tabla = '
        <table  border="0" cellpading="0" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9px" >
            <thead>
                <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
                    <th rowspan="2" style="border: 1px solid black; width: 25px; font-size: 9px">Corr</th>
                    <th rowspan="2" style="border: 1px solid black; width: 55px; font-size: 9px">Fecha</th>
                    <th rowspan="2" style="border: 1px solid black; width: 100px; font-size: 9px">No. de CCF</th>
                    <th rowspan="2" style="border: 1px solid black; width: 45px; font-size: 9px">N.R.C</th>
                    <th rowspan="2" style="border: 1px solid black; width: 180px; font-size: 9px">Proveedor</th>

                    <th colspan="3" style="border: 1px solid black; align: center; width: 85px; height: 15px; font-size: 9px">Compras exentas</th>
                    
                    <th colspan="3" style="border: 1px solid black; align: center; width: 145; font-size: 9px">Compras gravadas</th>

                    <th rowspan="2" style="border: 1px solid black; width: 48px; font-size: 9px">IVA percibido</th>
                    <th rowspan="2" style="border: 1px solid black; width: 55px; font-size: 9px">Total</th>

                </tr>
                <tr style="text-align: center; font-weight: bold; background-color: #f4f2ef">
                    <th style="border: 1px solid black; text-align: center; width: 42px; font-size: 9px">Internas</th>
                    <th style="border: 1px solid black; text-align: center; width: 43px; font-size: 9px">Internaciones</th>

                    <th style="border: 1px solid black; text-align: center; width: 55px;font-size: 9px">Internas</th>
                    <th style="border: 1px solid black; text-align: center; width: 40px;font-size: 9px">Impor</th>
                    <th style="border: 1px solid black; text-align: center; width: 50px;font-size: 9px">IVA C.F.</th>
                </tr>
            </thead>
            <tbody>';

        //contador
        $numero = 1;
        //variables para guardar las sumas
        $sumaGravadas = 0;
        $sumaIvaCompra = 0;
        $sumaIvaPercibido = 0;
        $sumaTotalCompra = 0;
        $sumaTotalCompraExenta = 0;
        // Iterar para mostrar cada compra en la tabla
        foreach ($compras as $item) {
            $sumaGravadas += $item->comprasGravadas;
            $sumaIvaCompra += $item -> ivaCompra;
            $sumaIvaPercibido += $item -> ivaPercibido;
            $sumaTotalCompra += $item->totalCompra;
            $sumaTotalCompraExenta += $item->comprasExentas;

            $tabla .= 
            '<tr style="border: 1px dotted gray; font-size: 8.5px">
                <td style="border: 1px dotted gray; width: 25px;">' . $numero++ . '</td>
                <td style="border: 1px dotted gray; width: 55px; ">' . $item->fecha . '</td>
                <td style="border: 1px dotted gray; width: 100px;">' . $item->numeroCCF . '</td>
                <td style="border: 1px dotted gray; width: 45px;">' . $item->nrc . '</td>
                <td style="border: 1px dotted gray; width: 180px;">' . $item->nombre . '</td>

                <td style="border: 1px dotted gray; width: 42px;">$' . number_format($item->comprasExentas, 2) . '</td>
                <td style="border: 1px dotted gray; width: 43px;">$' . "0.00" . '</td>

                <td style="border: 1px dotted gray; width: 55px">$ ' . number_format($item->comprasGravadas, 2) . '</td>
                <td style="border: 1px dotted gray; width: 40px">$ ' . "0.00". '</td>
                <td style="border: 1px dotted gray; width: 50px">$ ' . number_format($item->ivaCompra, 2) . '</td>

                <td style="border: 1px dotted gray; width: 48px">$ ' . number_format($item->ivaPercibido, 2) . '</td>
                <td style="border: 1px dotted gray; width: 55px">$ ' . number_format($item->totalCompra, 2) . '</td>
            </tr>';
        }

        // Añadir la fila de sumas al final de la tabla
        $tabla .= '
        <br>
        <tr style="font-weight: bold; font-size: 9px;">
            <td style="width: 405; height: 15px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black;">' . "SUMAS:" .'</td>

            <td style="width: 42px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$' . number_format($sumaTotalCompraExenta , 2). '</td>
            <td style="width: 43px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$' . "0.00" . '</td>

            <td style="width: 55px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$ ' . number_format($sumaGravadas, 2) . '</td>
            <td style="width: 40px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$' . "0.00" . '</td>
            <td style="width: 50px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$ ' . number_format($sumaIvaCompra, 2) . '</td>
            
            <td style="width: 48px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$ ' . number_format($sumaIvaPercibido, 2) . '</td>
            <td style="width: 55px; text-align: center; border-top: 1px solid black; border-bottom: 1px solid black; ">$ ' . number_format($sumaTotalCompra, 2) . '</td>
        </tr>

        </tbody></table>';


        // Escribir la tabla en el PDF
        $pdf->writeHTML($tabla, true, false, true, false, '');

        //Espacio para firma del contador
        $pdf->Ln(10);

        $pdf->Cell(90, 5, '___________________________________', 0, 1, 'R');
        $pdf->Cell(75, 5, $emisor->contador, 0, 1, 'R');

        //Nombre del contador
        $pdf->Cell(105, 5, $emisor->rol_contador, 0, 1, 'C');

        //Retorna el pdf
        return response($pdf->Output('Compras'  . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf');
    }


    //Retorna un excel con ventas a consumidor
    public function ConsumidorExcel($fecha)
    {
        [$anio, $mes] = explode('-', $fecha);

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
            ->whereYear('dte.fecha', $anio)
            ->whereMonth('dte.fecha', $mes)
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

    public function ContribuyenteExcel($fecha)
    {
        [$anio, $mes] = explode('-', $fecha);
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
            ->whereYear('dte.fecha', $anio)
            ->whereMonth('dte.fecha', $mes)
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
    public function comprasExcel($fecha)
    {
        [$anio, $mes] = explode('-', $fecha);

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
        $html .= '<p style="margin: 0;">Dirección: ' . $dte->ventas->cliente->direccion . ', ' . $dte->ventas->cliente->municipality_name . ', ' . $dte->ventas->cliente->department_name . '</p>';
        $html .= '<p style="margin: 0;">Teléfono: ' . $dte->ventas->cliente->telefono . '  ' . '  Correo: ' . $dte->ventas->cliente->correoElectronico . '</p>';
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
