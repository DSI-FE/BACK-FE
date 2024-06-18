<?php

namespace App\Models\Attendance;

use App\Models\Administration\Employee;
use App\Models\Attendance\PermissionType;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionRequest extends Model
{
    use HasFactory;

    protected $table = 'att_permission_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'date_ini',
        'date_end',
        'time_ini',
        'time_end',
        'justification',
        'state',
        'adm_employee_id',
        'adm_employee_generated_id',
        'adm_employee_boss_id',
        'adm_employee_hr_id',
        'att_permission_type_id',
        'boss_approved_at'
    ];
    
    public $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [];

    public function employee(): BelongsTo
    {
        return $this->BelongsTo(Employee::class, 'adm_employee_id','id');
    }

    public function employeeGenerated(): BelongsTo
    {
        return $this->BelongsTo(Employee::class, 'adm_employee_generated_id','id');
    }

    public function employeeBoss(): BelongsTo
    {
        return $this->BelongsTo(Employee::class, 'adm_employee_boss_id','id');
    }

    public function employeeHR(): BelongsTo
    {
        return $this->BelongsTo(Employee::class, 'adm_employee_hr_id','id');
    }

    public function permissionType(): BelongsTo
    {
        return $this->BelongsTo(PermissionType::class, 'att_permission_type_id','id');
    }

    public function scopeWithPermissionsBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereHas('permissions', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date_ini', [$startDate, $endDate])
                  ->orWhereBetween('date_end', [$startDate, $endDate]);
        });
    }

}