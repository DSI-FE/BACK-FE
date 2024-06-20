<?php

namespace App\Models\Proveedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProveedoresModel extends Model
{
    use HasFactory;
    protected $table = 'proveedores';
    protected $primaryKey = 'idProveedor';
    protected $fillable = ['idProveedor', 'codigo', 'nrc', 'nombre', 'tipo', 'nit', 'serie'];
}
