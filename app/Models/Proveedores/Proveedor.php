<?php

namespace App\Models\Proveedores;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Proveedor extends Model
{
    // Nombre de la tabla
    protected $table = 'proveedor';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'codigo',
        'nrc',
        'nombre',
        'nit',
        'serie',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Opcional: Si necesitas castear algún atributo
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
            ->useLogName('proveedor')
            ->logAll()
            ->logOnlyDirty();
    }

    // Definir relaciones si existen
    // public function relacion()
    // {
    //     return $this->belongsTo(RelacionModelo::class);
    // }
}
