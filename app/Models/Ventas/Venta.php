<?php
namespace App\Models\Ventas;

use App\Models\Clientes\Cliente;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    // Nombre de la tabla
    protected $table = 'ventas';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'total_no_sujetas',
        'total_exentas',
        'total_gravadas',
        'total_iva',
        'total_pagar',
        'estado',
        'condicion',
        'tipo_documento',
        'cliente_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('venta')
            ->logAll()
            ->logOnlyDirty();
    }

    //Definir relacion con el cliente
    public function cliente()
    {
       return $this->belongsTo(Cliente::class, 'cliente_id');
    }

     //Hacer referencia a detalle venta
     public function ventas()
     {
         return $this->hasMany('App\Models\Ventas\DetalleVenta', 'venta_id');
     }


}