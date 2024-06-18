<?php

namespace App\Helpers;

class StringsHelper
{
    public static function normalizarTexto($input)
    {
        $input = iconv('UTF-8', 'ASCII//TRANSLIT', $input);
        $input = preg_replace('/[^a-zA-Z0-9]/', '_', $input);
        $input = strtolower($input);
        return $input;
    }
}