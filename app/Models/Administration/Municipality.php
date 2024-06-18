<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Administration\Address;
use App\Models\Administration\Department;

class Municipality extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'adm_municipalities';

    protected $primaryKey  = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'name',
        'adm_department_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function department()
    {
        return $this->belongsTo(Department::class, 'adm_department_id');
    }

    public function addresses()
    {
        return $this->belongsTo(Address::class, 'adm_address_id');
    }
}
