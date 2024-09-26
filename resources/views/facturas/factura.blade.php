<!DOCTYPE html>
<html>
<head>
    <title>Factura</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>Factura #{{ $venta->id }}</h1>
    <p>Fecha: {{ $venta->fecha }}</p>
    <p>Cliente: {{ $venta->cliente->nombre }}</p>
    <p>Condición: {{ $venta->condicion->nombre }}</p>
    <p>Tipo de Documento: {{ $venta->tipo_documento->nombre }}</p>

    <h3>Detalles de la venta</h3>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto->nombre }}</td>
                    <td>{{ $detalle->cantidad }}</td>
                    <td>{{ $detalle->precio }}</td>
                    <td>{{ $detalle->total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Total a pagar: ${{ number_format($venta->total_pagar, 2) }}</p>
</body>
</html>
