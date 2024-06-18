<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0EA5E9;
            font-family: Arial, sans-serif;
        }

        .email-container {
            margin: 20px auto;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            width: 80%;
        }

        .email-content {
            border-top: 2px solid black;
            text-align: justify;
            padding: 15px;
        }

        .footer {
            font-size: 1.2em;
            border-top: 2px solid black;
            padding: 5px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-content">
            @yield('body')
        </div>
        <div class="footer">
            <strong>NOTA:</strong> <span style="color: #01a6fe;">Este es un correo generado automáticamente por el sistema informático de la DGEHM</span>
        </div>
    </div>
</body>

</html>
