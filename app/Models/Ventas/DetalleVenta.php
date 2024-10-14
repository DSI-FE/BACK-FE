<?php
namespace App\Models\Ventas;

use App\Models\Inventarios\Inventario;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetalleVenta extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'detalle_venta';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'cantidad',
        'precio',
        'iva',
        'total',
        'venta_id',
        'producto_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    //Definir la relacion con la Venta
    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    //Deifinir la relacion con producto
    public function producto()
    {
        return $this->belongsTo(Inventario::class, 'producto_id');
    }


 


}