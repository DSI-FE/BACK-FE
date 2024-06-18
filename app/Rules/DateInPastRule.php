<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class DateInPastRule implements ValidationRule
{
    protected $maxDaysInPast;
    protected $customMessage;
    
    public function __construct($maxDaysInPast,$customMessage=null)
    {
        $this->maxDaysInPast = $maxDaysInPast;
        $this->customMessage = $customMessage;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dateIni = Carbon::createFromFormat('Y-m-d', $value);
        $today = Carbon::now();

        // Calculate the difference in days between date_ini and today
        $daysDifference = $dateIni->diffInDays($today);

        if ($daysDifference > $this->maxDaysInPast) {
            $message = $this->customMessage ?? "$attribute no debe exceder $this->maxDaysInPast en el pasado";
            $fail($message);
        }
    }
}
