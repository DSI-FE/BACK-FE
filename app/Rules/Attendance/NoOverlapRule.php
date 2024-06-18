<?php

namespace App\Rules\Attendance;

use App\Models\Administration\Employee;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

use Closure;

class NoOverlapRule implements ValidationRule
{

    protected $customMessage;
    
    public function __construct( $customMessage = null )
    {
        $this->customMessage = $customMessage;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        
        $employeeId = request()->input('adm_employee_id');
        $employee = Employee::find($employeeId);

        $dateIni = $value;
        $dateEnd = request()->input('date_end');
        $timeIni = request()->input('time_ini');
        $timeEnd = request()->input('time_end');

        $dateIni = Carbon::createFromFormat('Y-m-d', $dateIni);
        $dateEnd = Carbon::createFromFormat('Y-m-d', $dateEnd);
        $timeIni = Carbon::createFromFormat(strlen($timeIni) === 5 ? 'H:i' : 'H:i:s', $timeIni)->setDate(2000, 1, 1);
        $timeEnd = Carbon::createFromFormat(strlen($timeEnd) === 5 ? 'H:i' : 'H:i:s', $timeEnd)->setDate(2000, 1, 1);

    }
}
