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
        'anulada_id',
        'contingencia_id'
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
    public function anulada()
    {
        return $this->belongsTo(VentasAnuladas::class, 'anulada_id');
    }
    public function contingencia()
    {
        return $this->belongsTo(Contingencia::class, 'contingencia_id');
    }
    public function tipoTransmision()
    {
        return $this->belongsTo(TipoTransmision::class, 'tipo_transmision');
    }
    public function modeloFacturacion()
    {
        return $this->belongsTo(ModeloFacturacion::class, 'modelo_facturacion');
    }
 

}