<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Administration\Employee;

class MaritalStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'adm_marital_statuses';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'active',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
