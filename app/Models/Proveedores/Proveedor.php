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
        'tipo_proveedor_id'  // Asegúrate de tener este campo en tu tabla
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Definir relación con TipoProveedor
    public function tipoProveedor()
    {
        return $this->belongsTo(TipoProveedor::class, 'tipo_proveedor_id');
    }

    // Atributo para obtener el nombre del tipo de proveedor
    public function getTipoProveedorNombreAttribute()
    {
        return $this->tipoProveedor ? $this->tipoProveedor->tipo : null;
    }

    // Para hacer visible este atributo en el JSON
    protected $appends = ['tipo_proveedor_nombre'];

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

    // Definir relaciones con la compra
    public function proveedores()
    {
        return $this->hasMany('App\Models\Proveedores\Proveedor', 'proveedor_id');
    }
}
