<?php

namespace App\Models\Proveedores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipoProveedor extends Model
{
    use HasFactory, SoftDeletes;

    // Nombre de la tabla
    protected $table = 'tipo_proveedor';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'tipo'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Definir relaciones
    public function proveedores()
    {
        return $this->hasMany('App\Models\Proveedores\Proveedor', 'tipo_proveedor_id');
    }
}
