<!DOCTYPE html>
<html>
<head>
    <title>Correo</title>
    <style type="text/css">
        /* Estilo base */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
            width: 100%;
        }

        .container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 8px;
            padding: 20px;
        }

        .content {
            padding: 20px;
            font-size: 16px;
            color: #333333;
        }

        .content p {
            margin: 10px 0;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 14px;
            color: #999999;
        }

        /* Estilo responsivo */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding: 10px;
            }

            .content {
                padding: 10px;
            }

            h2 {
                font-size: 22px;
            }

            p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body style="background-color: #fdfdfd; margin: 0; padding: 0;">
    <table style="width: 100%; background-color: #eef6f8; border-spacing: 0; border-collapse: collapse;">
        <tr>
            <td>
                <table class="container" style="background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 8px; width: 100%;">
                    <tr>
                        <td class="content" style="padding: 20px; font-size: 16px; color: #333333;">
                            <h2 style="text-align: center; color: #333333;">Documento tributario electrónico</h2>
                            <p>Estimado <strong>{{ $nombre}}</strong></p>
                            <p>Por este medio se adjunta la representación gráfica de la siguiente Factura Electrónica</p>
                            <p><strong>Fecha:</strong> <span style="color: #464343;">{{$fecha}}</span></p>
                            <p><strong>Código de generación:</strong> <span style="color: #464343;">{{$codigo_generacion}}</span></p>
                            <p><strong>Número de control:</strong> <span style="color: #464343;">{{$numero_control}}</span></p>
                            <p>{{ $detalle }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="footer" style="text-align: center; padding: 20px; font-size: 14px; color: #999999;">
                            <p>Gracias por su compra.</p>
                            <br>Este correo ha sido generado automáticamente</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
