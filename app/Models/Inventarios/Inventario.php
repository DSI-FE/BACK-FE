<?php

namespace App\Models\Inventarios;

use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inventario extends Model
{
    // Nombre de la tabla
    protected $table = 'inventario';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'equivalencia',
        'existencias',
        'precioCosto',
        'precioVenta',
        'producto_id',
        'unidad_medida_id',
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
            ->useLogName('inventario')
            ->logAll()
            ->logOnlyDirty();
    }

    //Definiendo la relacion con Productos
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    //Definir relacion con unidad de medida
    public function unidad()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    //Atributo para obtener el nombre del producto
    public function getNombreProductoAttribute()
    {
        return $this->producto ? $this->producto->nombreProducto : null;
    }

    //Atributo para obtener el nombre de la unidad de medida
    public function getUnidadMedidaAttribute()
    {
        return $this->unidad ? $this->unidad->nombreUnidad : null;
    }

    //para hacerlo visible en el json
    protected $appends = ['nombre_producto', 'unidad_medida'];

     // Definir relaciones con Detalle compra
     public function detalleCompra()
     {
         return $this->hasMany('App\Models\Compras\DetalleCompra', 'producto_id');
     }
}
