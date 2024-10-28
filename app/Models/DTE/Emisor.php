<?php
namespace App\Models\DTE;

use App\Models\Ventas\DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class Emisor extends Model
{
    // Nombre de la tabla
    protected $table = 'emisor';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombre',
        'nit',
        'nrc',
        'actividad_economica',
        'direccion',
        'telefono',
        'correo',
        'nombre_comercial',
        'tipo_establecimiento_id',
        'municipio_id',
        'departamento_id',
        'contador',
        'rol_contador',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

      
    // Definir relaciones
    public function department()
    {
        return $this->belongsTo('App\Models\Administration\Department', 'departamento_id');
    }

    public function municipality()
    {
        return $this->belongsTo('App\Models\Administration\Municipality', 'municipio_id');
    }

    public function economicActivity()
    {
        return $this->belongsTo('App\Models\Clientes\ActividadEconomica', 'actividad_economica');
    }

    public function establecimiento()
    {
        return $this->belongsTo('App\Models\DTE\TipoEstablecimiento', 'tipo_establecimiento_id');
    }

    //Atributo para obtener el nombre del establecimiento
    public function getEstablecimientoNameAttribute()
    {
        return $this->establecimiento->valores;
    }
    

    // Atributo para obtener el nombre del departamento
    public function getDepartmentNameAttribute()
    {
        return $this->department->name;
    }

    // Atributo para obtener el nombre del municipio
    public function getMunicipalityNameAttribute()
    {
        return $this->municipality->name;
    }

    // Atributo para obtener el nombre de la actividad económica
    public function getEconomicActivityNameAttribute()
    {
        return $this->economicActivity->actividad;
    }

    // Para hacer estos atributos visibles en el JSON
    protected $appends = ['department_name', 'municipality_name', 'economic_activity_name', 'establecimiento_name'];

}