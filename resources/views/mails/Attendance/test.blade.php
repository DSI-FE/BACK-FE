@extends('mails.master')
@section('body')
<h4>Permiso solicitado</h4>
<p><strong>{{$employeeApplicant}}</strong></p>

<blockquote>
    {{ $msg }}
</blockquote>

<blockquote>
    Acción realizada por {{ $employeeReceiver }}
</blockquote>
<blockquote style="border-top:2px solid black;text-align:justify;padding:15px">
    <p>Fecha Inicial: {{$dateIni}}</p>
    <p>Fecha Final: {{$dateEnd}}</p>
    <p>Hora Inicial: {{$timeIni}}</p>
    <p>Hora Final: {{$timeEnd}}</p>
    <p>Justificación: {{$description}}</p>
</blockquote>


<p>Verifique su buzón de solicitudes para más detalles sobre el permiso.</p>

<br>
<p>Para ingresar al sistema puede hacerlo a travéz del siguiente enlace <a href="#">DGEHM</a></p>

@endsection