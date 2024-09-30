<?php
namespace App\Models\DTE;

use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class DTE extends Model
{
    // Nombre de la tabla
    protected $table = 'dte';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'fecha',
        'hora',
        'tipo_transmision',
        'modelo_facturacion',
        'codigo_generacion',
        'numero_control',
        'sello_recepcion',
        'id_venta',
        'ambiente',
        'version',
        'moneda',
        'tipo_contingencia',
        'motivo_contingencia',
        'tipo_documento',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

      //Definir las relaciones
    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }
    public function ambiente()
    {
        return $this->belongsTo(Ambiente::class, 'ambiente');
    }
    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'moneda');
    }
    public function tipo()
    {
        return $this->belongsTo(TipoDocumento::class,  'tipo_documento');
    }
 

}