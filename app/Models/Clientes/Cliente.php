<?php

namespace App\Models\Clientes;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cliente extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

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
        'codigo',
        'nombres',
        'apellidos',
        'numeroDocumento',
        'direccion',
        'nrc',
        'telefono',
        'correoElectronico',
        'department_id',
        'municipality_id',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Opcional por si necesitamos castear algún atributo
    protected $casts = [
        // Por ejemplo, si 'nrc' se manejara como un array
        // 'nrc' => 'array',
    ];

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

    // Para hacer estos atributos visibles en el JSON
    protected $appends = ['department_name', 'municipality_name'];
}
