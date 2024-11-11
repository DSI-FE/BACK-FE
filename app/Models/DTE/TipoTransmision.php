<?php

namespace App\Models\DTE;


use Illuminate\Database\Eloquent\Model;

class TipoTransmision extends Model
{
    // Nombre de la tabla
    protected $table = 'tipo_transmision';

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

    // Definir relación con dte
    public function transmision()
    {
        return $this->hasMany('App\Models\DTE\DTE', 'tipo_transmision');
    }
}
