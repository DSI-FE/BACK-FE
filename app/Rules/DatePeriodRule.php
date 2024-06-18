<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class DatePeriodRule implements ValidationRule
{
    protected $maxDaysPerRequest;
    protected $customMessage;

    public function __construct($maxDaysPerRequest, $customMessage = null)
    {
        $this->maxDaysPerRequest = $maxDaysPerRequest;
        $this->customMessage = $customMessage;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dateIni = request()->input('date_ini') ? Carbon::createFromFormat('Y-m-d', request()->input('date_ini')) : Carbon::now();
        $dateEnd = Carbon::createFromFormat('Y-m-d', $value);

        $daysDifference = $dateIni->diffInDays($dateEnd);

        if ($daysDifference >= $this->maxDaysPerRequest) {
            $message = $this->customMessage ?? "El periodo formado por :attribute y date_ini no debe exceder los $this->maxDaysPerRequest días";
            $fail($message);
        }
    }
}
