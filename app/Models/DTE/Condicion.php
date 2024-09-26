<?php
namespace App\Models\DTE;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class Condicion extends Model
{
    // Nombre de la tabla
    protected $table = 'condicion_operacion';

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
        'deleted_at',
    ];

     // Definir relación con la venta
     public function venta()
     {
         return $this->hasMany(Venta::class, 'condicion');
     }
}