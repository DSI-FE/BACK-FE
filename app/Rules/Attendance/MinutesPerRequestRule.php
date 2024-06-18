<?php

namespace App\Rules\Attendance;

use App\Models\Administration\Employee;
use App\Models\Attendance\Holiday;

use Illuminate\Contracts\Validation\ValidationRule;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

use Closure;

class MinutesPerRequestRule implements ValidationRule
{
    protected $maxMinutes;
    protected $customMessage;
    
    public function __construct($maxMinutes,$customMessage=null)
    {
        $this->maxMinutes = $maxMinutes;
        $this->customMessage = $customMessage;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minutesRequested = 0;

        $employeeId = request()->input('adm_employee_id');
        $employee = Employee::find($employeeId);

        $dateIni = request()->input('date_ini');
        $dateEnd = request()->input('date_end');
        $timeIni = request()->input('time_ini');
        $timeEnd = $value;

        $timeIni = Carbon::createFromFormat(strlen($timeIni) === 5 ? 'H:i' : 'H:i:s', $timeIni)->setDate(2000, 1, 1);
        $timeEnd = Carbon::createFromFormat(strlen($timeEnd) === 5 ? 'H:i' : 'H:i:s', $timeEnd)->setDate(2000, 1, 1);
        
        if ( $dateIni && $dateEnd && $timeIni && $timeEnd ) {
            
            $period = CarbonPeriod::create( $dateIni, $dateEnd );
            foreach ( $period as $key => $date ) {
                
                $schedule = $employee?->activeSchedule($date->format('Y-m-d'));
                $scheduleDate = $schedule && count($schedule?->days) > 0 ? $schedule->days[0] : null;
                $scheduleDate = $scheduleDate->pivot ?? null;
                $scheduleIni = $scheduleDate ? $scheduleDate->time_start : null;
                $scheduleEnd = $scheduleDate ? $scheduleDate->time_end : null;
                
                if ( $scheduleIni && $scheduleEnd && count( Holiday::actualVacation( $date )->get() ) ===0 ) {
                    $scheduleIni = Carbon::createFromFormat(strlen($scheduleIni) === 5 ? 'H:i' : 'H:i:s', $scheduleIni)->setDate(2000, 1, 1);
                    $scheduleEnd = Carbon::createFromFormat(strlen($scheduleEnd) === 5 ? 'H:i' : 'H:i:s', $scheduleEnd)->setDate(2000, 1, 1);
                    if ( $dateIni === $dateEnd ) {
                        $minutesRequested = $timeEnd->diffInMinutes($timeIni);
                    } else {
                        if ( $dateIni === $date->format('Y-m-d') ) {
                            $timeIni = $timeIni >= $scheduleIni ?  $timeIni : $scheduleIni; // timeIni should be equal or greather than scheduleIni
                            $timeIni = $timeIni >= $scheduleEnd ?  $scheduleEnd : $timeIni; // timeIni should be less than scheduleEnd
                            $minutesRequested += $scheduleEnd->diffInMinutes($timeIni); // minutes requested in the first date is equal to the diff between the scheduleEnd and the timeIni
                        } else if ( $dateEnd === $date->format('Y-m-d') ) {
                            $timeEnd = $timeEnd <= $scheduleEnd ?  $timeEnd : $scheduleEnd; // timeEnd should be equal or lesser than scheduleEnd
                            $timeEnd = $timeEnd <= $scheduleIni ?  $scheduleIni : $timeEnd; // timeIni should be less than scheduleEnd
                            $minutesRequested += $timeEnd->diffInMinutes($scheduleIni); // minutes requested in the end date is equal to the diff between the scheduleIni and the timeEnd
                        } else {
                            $minutesRequested += $scheduleEnd->diffInMinutes($scheduleIni);
                        }
                    }
                }
            }
        }

        if ($minutesRequested >  $this->maxMinutes) {
            

            if( $this->maxMinutes > 90 ) {
                $maxMinutes = $this->maxMinutes/60;
                $message = $this->customMessage ?? "No puedes solicitar mas de $maxMinutes horas";
            } else {
                $message = $this->customMessage ?? "No puedes solicitar mas de $this->maxMinutes minutos";
            }
            $fail($message);

            
        }
    
    }
}
