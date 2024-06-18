<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body {
            font-family: 'Museo Sans 300', sans-serif;
        }
    </style>
</head>

<body style="text-align: center; vertical-align: middle; margin: 0; background-color: #0EA5E9; padding: 20px; display: flex; align-items: center; justify-content: center;">
    <div style="width: 70%; background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); text-align: left; margin: 0 auto;">
        <h4>Ha recibido una nueva solicitud de transporte</h4>
        <blockquote>
            <p>{{ $name }} {{ $lastName }}, ha enviado la solicitud de transporte N° {{ $transport_id }}.</p>
            <p>Está solicitando transporte para {{ $title }} en {{ $destiny }}</p>
            <p>De clic aquí para ver más detalles y evaluar la solicitud</p>
            <div style="margin-top: 20px;">
                <a href="{{ config('app.url') }}/transport/PanelAdmin" style="background-color: #0EA5E9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Evaluación de solicitudes</a>
            </div>
        </blockquote>
        <p>Departamento de Transporte</p>
        <p>Gerencia Administrativa</p>
        <div style="font-size: 0.9em; border-top: 2px solid black; padding: 5px;">
            <strong>NOTA:</strong> Este es un correo automatizado y enviado desde el sistema informático de la DGEHM.
        </div>
    </div>
</body>

</html>