<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


use App\Models\Administration\Employee;
use App\Models\Attendance\Device;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Marking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'att_markings';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'datetime',
        'type',
        'time_late',
        'time_early',
        'courtesy_minutes',
        'adm_employee_id',
        'device_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('remote_mark')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employee(): BelongsTo
    {
        return  $this->belongsTo(Employee::class, 'adm_employee_id','id');
    }

    public function device(): BelongsTo
    {
        return  $this->belongsTo(Device::class, 'device_id','id');
    }

    public function scopeByDateEmployeeType(Builder $query,$date,$employeeId,$type): void
    {
        $query->whereDate('datetime',$date)->where('adm_employee_id',$employeeId)->where('type',$type);
    }

    public function scopeByDateEmployee(Builder $query,$date,$employeeId): void
    {
        $query->whereDate('datetime',$date)->where('adm_employee_id',$employeeId);
    }
}
