<?php

namespace App\Models\DTE;


use Illuminate\Database\Eloquent\Model;

class TipoInvalidacion extends Model
{
    // Nombre de la tabla
    protected $table = 'tipo_invalidacion';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombre'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    // Definir relación con emisor
    public function establecimiento()
    {
        return $this->hasMany('App\Models\DTE\DTE', 'tipo_invalidacion_id');
    }
}
