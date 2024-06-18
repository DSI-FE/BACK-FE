<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeRequestType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_request_types';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
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
            ->useLogName('employee_request_type')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employeeRequests()
    {
        return $this->hasMany(EmployeeRequest::class);
    }

    public function employeeRequestTypeElements()
    {
        return $this->hasMany(EmployeeRequestTypeElement::class, 'adm_request_type_id');
    }
}
