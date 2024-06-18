<table cellpadding="5">
    <tr>
        <td>
            <b>Nombre:</b> {{$nombre}}
        </td>
    </tr>
    <tr>
        <td>
            <b>Cargo Funcional:</b> {{$cargo}}
        </td>
    </tr>
    <tr>
        <td>
            <b>Tipo de Permiso:</b> {{$tipo}}
        </td>
    </tr>
    <tr>
        <td>
            @if (empty($fecha_final) || $fecha_inicial === $fecha_final)
            <b>Fecha Solicitada:</b> {{$fecha_inicial}}
            @else
            <b>Periodo Solicitado:</b> {{$fecha_inicial}} - {{$fecha_final}}
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <b>Tiempo requerido:</b> {{$hora_inicial}} a {{$hora_final}}
        </td>
    </tr>
    <tr>
        <td>
            <table width="1050">
                <tr>
                    <td width="80"><b>Justificante:</b></td>
                    <td>{{$justificante}}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>
<br>
<br>
<br>
<br>
<table style="text-align:center" cellspacing="35">
    <tr>
        <td>
            <div style="border-top:1px solid black;">{{$nombre}}</div>
            <span>Colaborador</span><br>
            {{$fecha_enviada}}
        </td>
        <td>
            <div style="margin:0; border-top:1px solid black;">{{$boss_name}}</div>
            <span>Jefe Inmediato</span><br>
            @if ($boss_approved_at)
            {{$boss_approved_at}}
            @else
            @endif
        </td>
    </tr>
</table>
<!-- <p><b>NOTA</b> Se detalla los casos en los cuales se requiere la firma del Director:</p>
<ul style="font-size: small;text-align: justify;">
    <li>
        Art. 13.- Ley de Creación del Consejo Nacional de Energía entre las atribuciones y deberes del Secretario Ejecutivo están el Literal “h) Nombrar, ascender,sancionar, remover y conceder licencias al personal de conformidad con las normas
        legales y reglamentarias.
    </li>
    <li>
        Art. 74.- Reglamento Interno de Trabajo Son obligaciones del Consejo y de sus representantes patronales f) Conceder licencia al funcionario o empleado:
        <ul>
            <li>8. Se concederá licencia formal, con goce de sueldo, en caso de enfermedad prolongada, hasta por quince días por cada año de servicio. Estas licencias serán acumulativas; pero el derecho acumulado no pasará en ningún caso de tres
                meses.</li>
            <li>11. Por el tiempo que fuere necesario para los funcionarios o empleados que disfruten de becas para hacer estudios fuera del país, en virtud de compromisos internacionales suscritos por el Gobierno de la República en los cuales se
                especifique que la beca es pagada por gobiernos o instituciones extranjeras; cuando también la beca sea costeada por gobiernos o instituciones extranjeras aunque no medie convenio internacional; o para que asistan a Escuelas de
                Administración Pública, Centros o Cursos de Capacitación o Adiestramiento, organizados o impartidos en el país, costeados por el Gobierno exclusivamente o con la cooperación de Organismos Internacionales. La Licencia será concedida
                por el Secretario con goce de sueldo ya sea total o parcial.</li>
            <li>12. Por el tiempo necesario para el desempeño de misiones oficiales de carácter temporal tanto fuera como dentro del país autorizado previamente por el Secretario, y para asistir a reuniones, conferencias, congresos y otros eventos
                nacionales e internacionales a los que hubieren sido designados por el Consejo y autorizado previamente por el Secretario;</li>
        </ul>
    </li> -->
</ul>