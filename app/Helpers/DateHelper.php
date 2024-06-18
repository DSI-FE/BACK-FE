<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

class DateHelper
{

    private static $fullMonthNames = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    private static $fullDayNames = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo'
    ];

    private static $shortMonthNames = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];

    private static $shortDayNames = [
        1 => 'lun',
        2 => 'mar',
        3 => 'mie',
        4 => 'jue',
        5 => 'vie',
        6 => 'sab',
        7 => 'dom'
    ];

    public static function localDate($date)
    {

        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];

        $carbonDate = Carbon::createFromFormat('Y-m-d',$date);
        $formatedDate = $days[$carbonDate->dayOfWeek].' '.$carbonDate->day.' de '.$months[$carbonDate->month].' del '.$carbonDate->year;
        return $formatedDate;
    }

    /**
    * Converts a given date to a string format.
    *
    * @param Carbon $date             The date to be formatted.
    * @param bool   $showWeekDay      Indicates if the weekday should be included.
    * @param bool   $useShortNames      Indicates if contracted names should be used.
    * @param bool   $showNames        Indicates if month names should be displayed instead of numbers.
    * @return string                  Formatted date string.
    */

    public static function dateToString($date, $showWeekDay=false, $useShortNames=true, $showNames=false)
    {

        $weekDay = '';

        if ($showWeekDay) {
            if ($useShortNames) {
                $weekDay = self::$shortDayNames[$date->dayOfWeek + 1];
            } else {
                $weekDay = self::$fullDayNames[$date->dayOfWeek + 1] . ' ';
            }
        }

        $month = $date->format('m');

        if ($showNames) {
            if ($useShortNames) {
                $month = self::$shortMonthNames[$date->month];
            } else {
                $month = self::$fullMonthNames[$date->month] . ' ';
            }
        }
        
        return $weekDay.$date->day.'/'.$month.'/'.$date->year;
    }

}