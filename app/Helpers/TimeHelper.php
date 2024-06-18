<?php

namespace App\Helpers;

use Carbon\Carbon;

class TimeHelper
{
    public static function getOverlapInMinutes($ini1,$end1,$ini2,$end2)
    {
        $dif = NULL;
        if ($ini1 <= $end2 && $ini2 <= $end1)
        {
            $overlapStart = max($ini1, $ini2);
            $overlapEnd = min($end1, $end2);
            $carbonTime1 = Carbon::parse($overlapStart);
            $carbonTime2 = Carbon::parse($overlapEnd);
            $dif = $carbonTime1->diffInSeconds($carbonTime2)/60;
        }
        return $dif;
    }
}