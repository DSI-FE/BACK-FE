<?php
namespace App\Models\Clientes;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cliente extends Model
{

    protected $table = 'cliente';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

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

    public function getDepartmentNameAttribute()
    {
        return $this->department->name?? null;
    }

    public function getMunicipalityNameAttribute()
    {
        return $this->municipality->name ?? null;
    }

    public function getEconomicActivityNameAttribute()
    {
        return $this->economicActivity->actividad ?? null;
    }

    protected $appends = ['department_name', 'municipality_name', 'economic_activity_name'];

     public function ventas()
     {
         return $this->hasMany('App\Models\Ventas\Venta', 'cliente_id');
     }
}
