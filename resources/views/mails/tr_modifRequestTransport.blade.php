@extends('mails.master')
@section('body')
    <h4>Cambios en su solicitud de transporte</h4>
    <blockquote>
        <p>Estimada/o {{ $name }} {{ $lastName }},</p>
        <p>Su solicitud de transporte N° {{ $transport_id}}, para {{ $title }}</p>
        <p>ha cambiado su estado a: {{ $status}}</p>
        <p>Para ver los detalles ingrese aqui: </p>
    </blockquote>
    <p>Departamento de Transporte</p>
    <p>Gerencia Administrativa</p>
@endsection
