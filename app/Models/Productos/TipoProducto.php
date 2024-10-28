<?php
namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Model;


class TipoProducto extends Model
{

    // Nombre de la tabla
    protected $table = 'tipo_producto';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'nombre',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    // Definir relaciones con productos
    public function inventario()
    {
        return $this->hasMany('App\Models\Productos\Producto', 'tipo_producto_id');
    }
    

}