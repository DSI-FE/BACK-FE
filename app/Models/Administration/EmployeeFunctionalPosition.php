<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Administration\Employee;
use App\Models\Administration\FunctionalPosition;

class EmployeeFunctionalPosition extends Pivot
{
    use HasFactory;

    protected $table = 'adm_employee_adm_functional_position';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'date_start',
        'date_end',
        'principal',
        'salary',
        'active',
        'adm_employee_id',
        'adm_functional_position_id'
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

    public function functionalPosition()
    {
        return $this->belongsTo(FunctionalPosition::class, 'adm_functional_position_id','id');
    }
}
