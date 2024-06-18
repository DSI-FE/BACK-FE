<?php

namespace App\Rules\Attendance;

use App\Models\Attendance\Holiday;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Carbon\Carbon;

class HolidayOverlapRule implements ValidationRule
{

    protected $adjacentToHoliday;
    protected $customMessage;
    
    public function __construct($adjacentToHoliday,$customMessage=null)
    {
        $this->adjacentToHoliday = $adjacentToHoliday;
        $this->customMessage = $customMessage;
    }
    
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if(!$this->adjacentToHoliday)
        {
            $isValid = true;
            $holidays = Holiday::thisYear()->where('allow_adjacent_permissions',0)->get();
            $dateIni = $value;
            $dateEnd = request()->input('date_end');

            foreach ($holidays as $holiday) {

                $startDateAdj = Carbon::parse($holiday->date_start)->subDay();
                $startDateAdjIsWeekend = $startDateAdj->isWeekend();
                while($startDateAdjIsWeekend) {
                    $startDateAdj = $startDateAdj->subDay();
                    $startDateAdjIsWeekend = $startDateAdj->isWeekend();
                }

                $endDateAdj = Carbon::parse($holiday->date_end)->addDay();
                $endDateAdjIsWeekend = $endDateAdj->isWeekend();
                while($endDateAdjIsWeekend) {
                    $endDateAdj = $endDateAdj->addDay();
                    $endDateAdjIsWeekend = $endDateAdj->isWeekend();
                }

                if ( $startDateAdj->format('Y-m-d') <= $dateEnd && $endDateAdj->format('Y-m-d') >= $dateIni ) {
                    $isValid = false;
                }
            }

            if ( !$isValid ) {
                $message = $this->customMessage ?? "Fecha seleccionada no permitida por fecha festiva";
                $fail($message);
            }
        }

    }
}
