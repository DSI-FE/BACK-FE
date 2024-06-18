<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\Attendance\Permission;
use App\Models\Attendance\PermissionType;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeePermissionType extends Pivot
{
    use HasFactory;

    protected $table = 'adm_employee_att_permission_type';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;
    public $timestamps = false;
    public $fillable =
    [
        'adm_employee_id',
        'att_permission_type_id',
        'used_minutes',
        'used_requests',
        'month'
    ];

    protected $casts = [];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'adm_employee_att_permission_type_id', 'id');
    }

    public function permissionsBetweenDates($startDate, $endDate)
    {
        return $this->permissions()
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date_ini', [$startDate, $endDate])
                              ->orWhereBetween('date_end', [$startDate, $endDate]);
                    })
                    ->get();
    }

    public function permissionsInDate($date)
    {
        return $this->permissions()
                    ->where(function ($query) use ($date) {
                        $query->whereDate('date_ini','=',$date);
                    })
                    ->count();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class,'adm_employee_id','id');
    }

    public function permissionType()
    {
        return $this->belongsTo(PermissionType::class, 'att_permission_type_id','id');
    }

    public static function getTotalSum($employeeId, $permissionTypeId)
    {
        return self::selectRaw('SUM(used_minutes) as total_minutes, SUM(used_requests) as total_requests')
            ->where('adm_employee_id', $employeeId)
            ->where('att_permission_type_id', $permissionTypeId)
            ->first();
    }

}