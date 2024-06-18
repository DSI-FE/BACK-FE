<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Administration\Employee;
use App\Models\Administration\Department;
use App\Models\Administration\Municipality;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Address extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_addresses';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'urbanization',
        'street',
        'number',
        'complement',
        'adm_municipality_id',
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
            ->useLogName('address')
            ->logAll()
            ->logOnlyDirty();
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class,'adm_municipality_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
