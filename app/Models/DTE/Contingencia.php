<?php
namespace App\Models\DTE;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class Contingencia extends Model
{
    // Nombre de la tabla
    protected $table = 'contingencia';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'fechaInicio',
        'fechaFin',
        'horaInicio',
        'horaFin',
        'tipo_contingencia_id',
        'motivo_contingencia',
        'estado_contingencia',
        'sello_recepcion',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

     // Definir relación con el dte
     public function dte()
     {
         return $this->hasMany(DTE::class, 'contingencia_id');
     }

        // Definir relación con el tipo de contingencia
        public function tipoContingencia()
        {
            return $this->belongsTo(TipoContingencia::class, 'tipo_contingencia_id');
        }
}