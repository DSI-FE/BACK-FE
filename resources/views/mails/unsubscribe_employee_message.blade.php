<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dar de Baja a {{ $name }} {{ $lastname }}</title>
</head>
<body>
    <h3>Dar de Baja al siguiente Empleado</h3>
    <p>
        <div>Nombre:</div>
        <b>{{ $name }} {{ $lastname }}</b>
    </p>
    <p>
        <div>Email:</div>
        <b>{{ $email }}</b>
    </p>
    @if ($phone)
        <p>
            <div>Teléfono:</div>
            <b>{{ $phone }}</b>
        </p>
    @endif
    <p>
        <div>Justificacion:</div>
        <div>{{ $justification }}</div>
    </p>
    <p>Atte.</p>
    <p><b>Sistema ERP DGEHM</b></p>
</body>
</html>
