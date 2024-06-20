<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesModel extends Model
{
    use HasFactory;

    protected $table = 'clientes';
    protected $primaryKey = 'idCliente';
    protected $fillable = ['idCliente', 'codigo', 'nombres', 'apellidos', 'tipoDocumento', 'numeroDocumento', 'departamento', 'municipio', 'direccion', 'nrc', 'actividadEconomica', 'telefono', 'correo'];
}
