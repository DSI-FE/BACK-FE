<?php
namespace App\Models\DTE;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class TipoContingencia extends Model
{
    // Nombre de la tabla
    protected $table = 'tipo_contingencia';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombre',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

     // Definir relación con el dte
     public function dte()
     {
         return $this->hasMany(DTE::class, 'contingencia_id');
     }
}