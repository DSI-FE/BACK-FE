@extends('mails.master')
@section('body')
    <h4>Nuevo Colaborador Creado</h4>
    <blockquote>
        <p>
            <div><b>Nombre: </b> {{ $employee_name }}</div>
        </p>
        <p>
            <div><b>Posición Funcional: </b> {{ $functional_position }}</div>
        </p>
        <p>
            <div><b>Unidad Organizativa: </b> {{ $organizational_unit }}</div>
        </p>
    </blockquote>
    <p>Atte.</p>
    <p><b>Sistema ERP DGEHM</b></p>
@endsection
