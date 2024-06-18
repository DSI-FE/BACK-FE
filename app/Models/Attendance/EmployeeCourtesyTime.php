<?php

namespace App\Models\Attendance;

use App\Models\Administration\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeCourtesyTime extends Pivot
{
    use HasFactory;

    protected $table = 'att_employee_courtesy_time';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;
    public $timestamps = false;
    public $fillable =
    [
        'adm_employee_id',
        'month',
        'available_minutes',
        'used_minutes',
    ];

    protected $casts = [];
    
    public function employee()
    {
        return $this->belongsTo(Employee::class,'adm_employee_id','id');
    }
}