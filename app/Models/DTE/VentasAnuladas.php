<?php

namespace App\Models\DTE;


use Illuminate\Database\Eloquent\Model;

class VentasAnuladas extends Model
{
    // Nombre de la tabla
    protected $table = 'ventas_anuladas';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'tipo_invalidacion_id',
        'motivo_invalidacion',
        'responsable_id',
        'solicitante_id',
        'codigo_generacion_reemplazo'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    // Definir relación con dt
    public function dte()
    {
        return $this->hasMany('App\Models\DTE\DTE', 'anulada_id');
    }

    // Definir relación con tipo_invalidacion
    public function tipoInvalidacion()
    {
        return $this->belongsTo('App\Models\DTE\TipoInvalidacion', 'tipo_invalidacion_id');
    }

    // Definir relación con responsable
    public function responsableAnular()
    {
        return $this->belongsTo('App\Models\DTE\Responsable', 'responsable_id');
    }

    // Definir relación con solicitante
    public function solicitanteAnular()
    {
        return $this->belongsTo('App\Models\Clientes\Cliente', 'solicitante_id');
    }
}
