<?php
namespace App\Models\MH;


use Illuminate\Database\Eloquent\Model;

class DteAuth extends Model
{
    // Nombre de la tabla
    protected $table = 'dte_auth';

    // Clave primaria de la tabla
    protected $primaryKey = 'id';

    // Indica que el modelo tiene claves primarias autoincrementales
    public $incrementing = true;

    // Tipo de clave primaria
    protected $keyType = 'int';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'user',
        'pwd',
        'token',
    ];

    public $hidden = [
        'created_at',
        'updated_at'
    ];

}