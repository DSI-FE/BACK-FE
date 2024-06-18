<?php

namespace App\Models\Attendance;

use App\Models\Administration\Employee;
use App\Models\Attendance\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_schedules';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'description',
        'active',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot'
    ];

    protected $casts = [];

    public function days() : BelongsToMany
    {
        return  $this->belongsToMany(Day::class, 'att_day_att_schedule', 'att_schedule_id', 'att_day_id')
                ->withPivot(['time_start', 'time_end']);
    }

    public function employees(): BelongsToMany
    {
        return  $this->belongsToMany(Employee::class, 'adm_employee_att_schedule', 'att_schedule_id', 'adm_employee_id')
                ->using(EmployeeSchedule::class)
                ->withPivot(['date_start', 'date_end', 'active'])
                ->as('employeeSchedule');
    }
}
