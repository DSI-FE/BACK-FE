<?php

namespace App\Models\DTE;


use Illuminate\Database\Eloquent\Model;

class Responsable extends Model
{
    // Nombre de la tabla
    protected $table = 'responsable';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombre',
        'tipo_documento_id',
        'numero_documento',
        'emisor_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    // Definir relación con emisor
    public function emisor()
    {
        return $this->belongsTo('App\Models\DTE\Emisor', 'emisor_id');
    }
    // Definir relación con tipo de documento
    public function tipoDocumento()
    {
        return $this->belongsTo('App\Models\DTE\Identificacion', 'tipo_documento_id');
    }
}
