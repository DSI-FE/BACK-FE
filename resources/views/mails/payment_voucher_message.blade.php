<?php
$months = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];
?>

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
  </head>
  <body>
    <p>@if ($employee->gender->id === 2) ¡Hola! @elseif ($employee->gender->id === 1) ¡Hola! @else ¡Hola! @endif {{ $employee->name }},</p>
    <img src="cid:notification_image.jpg" width="100%" alt="Dirección General de Energía, Hidrocarburos y Minas">
  </body>
</html>
