<?php

namespace App\Models\Attendance;

use App\Models\Attendance\Step;
use Spatie\Activitylog\LogOptions;
use App\Models\Administration\Employee;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Attendance\EmployeePermissionType;
use App\Models\Attendance\PermissionRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PermissionType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'att_permission_types';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'name',
        'description',
        'minutes_per_year',
        'minutes_per_month',
        'minutes_per_request',
        'requests_per_year',
        'requests_per_month',
        'days_per_request',
        'days_before_request',
        'discount_applies',
        'adjacent_to_holiday',
        'later_days',
        'active',
        'dashboard_herarchy',
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
            ->useLogName('att_permission_type')
            ->logAll()
            ->logOnlyDirty();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class, 'att_permission_type_id');
    }

    public function employees(): BelongsToMany
    {
        return  $this->belongsToMany(Employee::class, 'adm_employee_att_permission_type', 'att_permission_type_id', 'adm_employee_id')
                ->using(EmployeePermissionType::class)
                ->withPivot(['used_minutes', 'used_requests', 'month'])
                ->as('employeePermissionType');
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'att_permission_type_id','id');
    }

}