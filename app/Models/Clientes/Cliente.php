<?php
namespace App\Models\Clientes;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cliente extends Model
{
    // Nombre de la tabla
    protected $table = 'cliente';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombres',
        'apellidos',
        'tipoIdentificacion',
        'numeroDocumento',
        'direccion',
        'nrc',
        'telefono',
        'correoElectronico',
        'department_id',
        'municipality_id',
        'economic_activity_id',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Opcional: Si necesitas castear algún atributo
    protected $casts = [];

    protected static $recordEvents = [
        'created',
        'updated',
        'deleted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cliente')
            ->logAll()
            ->logOnlyDirty();
    }

    // Definir relaciones
    public function department()
    {
        return $this->belongsTo('App\Models\Administration\Department', 'department_id');
    }

    public function municipality()
    {
        return $this->belongsTo('App\Models\Administration\Municipality', 'municipality_id');
    }

    public function economicActivity()
    {
        return $this->belongsTo('App\Models\Clientes\ActividadEconomica', 'economic_activity_id');
    }
    public function identificacion(){
        return $this->belongsTo('App\Models\DTE\Identificacion', 'tipoIdentificacion');
    }

    // Atributo para obtener el nombre del departamento
    public function getDepartmentNameAttribute()
    {
        return $this->department->name?? null;
    }

    // Atributo para obtener el nombre del municipio
    public function getMunicipalityNameAttribute()
    {
        return $this->municipality->name ?? null;
    }

    // Atributo para obtener el nombre de la actividad económica
    public function getEconomicActivityNameAttribute()
    {
        return $this->economicActivity->actividad ?? null;
    }

    // Para hacer estos atributos visibles en el JSON
    protected $appends = ['department_name', 'municipality_name', 'economic_activity_name'];

     //Hacer referencia a la venta
     public function ventas()
     {
         return $this->hasMany('App\Models\Ventas\Venta', 'cliente_id');
     }
}
