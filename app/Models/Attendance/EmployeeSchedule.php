<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Attendance\Schedule;
use App\Models\Administration\Employee;

class EmployeeSchedule extends Pivot
{
    use HasFactory;

    protected $table = 'adm_employee_att_schedule';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'date_start',
        'date_end',
        'active',
        'adm_employee_id',
        'att_schedule_id'
    ];

    public $hidden =
    [
        'created_at',
        'updated_at',
    ];
    
    protected $casts = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class,'adm_employee_id','id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'att_schedule_id','id');
    }

}