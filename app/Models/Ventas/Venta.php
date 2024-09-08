<?php
namespace App\Models\Ventas;

use App\Models\Clientes\Cliente;
use App\Models\DTE\Condicion;
use App\Models\DTE\TipoDocumento;
use Illuminate\Database\Eloquent\Model;
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
        'fecha',
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

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];


    // Definir relación con el cliente
    public function cliente()
    {
       return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    //Definir la relacion con la condicion de pago
    public function condicion()
    {
       return $this->belongsTo(Condicion::class, 'condicion');
    }
    //Definir la relacion con los tipos de documento
    public function tipo_documento()
    {
       return $this->belongsTo(TipoDocumento::class, 'tipo_documento');
    }

    // Atributo para obtener el nombre del cliente
    public function getClienteNombreAttribute()
    {
        return $this->cliente ? $this->cliente->nombres. ' '. $this->cliente->apellidos : null;
        
    }

    // Para hacer visible este atributo en el JSON
    protected $appends = ['cliente_nombre'];

    // Hacer referencia a detalle venta
    public function detalles()
    {
        return $this->hasMany('App\Models\Ventas\DetalleVenta', 'venta_id');
    }
}
