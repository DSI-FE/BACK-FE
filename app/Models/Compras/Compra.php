<?php
namespace App\Models\Compras;

use App\Models\Proveedores\Proveedor;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Compra extends Model
{
    use HasFactory, SoftDeletes;

    // Nombre de la tabla
    protected $table = 'compras';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'fecha',
        'numeroCCF',
        'comprasExentas',
        'comprasGravadas',
        'ivaCompra',
        'ivaPercibido',
        'totalCompra',
        'proveedor_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

     // Definir relación con el proveedor
     public function proveedor()
     {
         return $this->belongsTo(Proveedor::class, 'proveedor_id');
     }
 
     // Atributo para obtener el nombre del tipo de proveedor
     public function getProveedorNombreAttribute()
     {
         return $this->proveedor ? $this->proveedor->nombre : null;
     }
 
     // Para hacer visible este atributo en el JSON
     protected $appends = ['proveedor_nombre'];

     
      // Definir relaciones con DetalleCompra
    public function detalleCompra()
    {
        return $this->hasMany('App\Models\Compras\DetalleCompra', 'compra_id');
    }

}