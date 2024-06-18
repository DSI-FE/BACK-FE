@extends('mails.master')
@section('body')

<h4>Cancelación de solicitud de transporte</h4>

<blockquote>
<p>Estimada/o {{ $name }} {{ $lastName }},</p>
<p>Se ha cancelado la solicitud de transporte N° ...</p>
<p>Datos:</p>
</blockquote>

<p>Departamento de Transporte</p>
<p>Gerencia Administrativa</p>
@endsection