<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Administration\Address;
use App\Models\Administration\Municipality;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'adm_departments';

    protected $primaryKey  = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'name',
        'active'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function municipalities()
    {
        return $this->hasMany(Municipality::class, 'adm_department_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
    
    public function clientes()
    {
        return $this->hasMany('App\Models\Clientes\Cliente', 'department_id');
    }
}
