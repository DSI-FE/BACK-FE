<?php
namespace App\Models\Productos;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnidadMedida extends Model
{
    use HasFactory, SoftDeletes;

    // Nombre de la tabla
    protected $table = 'unidadmedida';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'codigo',
        'nombreUnidad'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

     // Definir relaciones con el inventario
     public function inventario()
     {
         return $this->hasMany('App\Models\Inventarios\Inventario', 'unidad_medida_id');
     }

     // Definir relaciones con el DetalleCompra
     public function detalleCompra()
     {
         return $this->hasMany('App\Models\Compras\DetalleCompra', 'unidad_medida_id');
     }


}