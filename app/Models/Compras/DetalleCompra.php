<?php
namespace App\Models\Compras;

use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetalleCompra extends Model
{
    use HasFactory, SoftDeletes;

    // Nombre de la tabla
    protected $table = 'detallecompra';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'cantidad',
        'costo',
        'iva',
        'total',
        'compra_id',
        'producto_id',
        'unidad_medida_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

      // Opcional: Si necesitas castear algún atributo
      protected $casts = [];



      public function getActivitylogOptions(): LogOptions
      {
          return LogOptions::defaults()
              ->useLogName('detallecompra')
              ->logAll()
              ->logOnlyDirty();
      }
  

     // Definir relación con Compra
     public function compra()
     {
         return $this->belongsTo(Compra::class, 'compra_id');
     }

     //definir relacion con el producto
     public function producto()
     {
        return $this->belongsTo(Producto::class, 'producto_id');
     }

     //Definir relacion con la unidad de medida
     public function unidad()
     {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
     }

 
     // Atributo para obtener el nombre del tipo de proveedor
     public function getNumeroCcfAttribute()
     {
         return $this->compra ? $this->compra->numeroCCF : null;
     }

     // Atributo para obtener el nombre del producto
     public function getIdProductoAttribute()
     {
         return $this->producto ? $this->producto->id : null;
     }

     // Atributo para obtener el nombre del producto
     public function getUnidadMedidaAttribute()
     {
         return $this->unidad ? $this->unidad->nombreUnidad : null;
     }
 
 
     // Para hacer visible este atributo en el JSON
     protected $appends = ['numero_ccf', 'id_producto', 'unidad_medida'];

}