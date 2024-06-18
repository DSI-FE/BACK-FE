<?php

namespace App\Models\Attendance;

use Spatie\Activitylog\LogOptions;
use App\Models\Attendance\Attachments;
use App\Models\Administration\Employee;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance\PermissionType;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;

class Step extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'att_steps';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'description',
        'global',
        'managed_by_boss',
        'managed_by_supplicant',
        'correlative',
        'hours_required',
        'att_permission_type_id'
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
            ->useLogName('att_step')
            ->logAll()
            ->logOnlyDirty();
    }

    public function permissionType()
    {
        return $this->belongsTo(PermissionType::class, 'att_permission_type_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachments::class, 'att_step_id','id');
    }

    public function employees()
    {
        return  $this->belongsToMany(Employee::class, 'adm_employee_att_step', 'att_step_id', 'adm_employee_id');
    }

    public function organizationalUnits()
    {
        return  $this->belongsToMany(OrganizationalUnit::class, 'adm_organizational_unit_att_step', 'att_step_id', 'adm_organizational_unit_id');
    }
}