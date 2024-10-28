<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\DTE\TipoContingencia;

class TipoContingenciaController extends Controller
{
    //Obtener los tipos de contingencia
    public function index()
    {
        // Obtener los tipos de contingencia de la base de datos
        $tiposContingencia = TipoContingencia::all();

        // Devolver la respuesta en formato JSON
        return response()->json([
            'message' => 'Lista de tipos de contingencia',
            'data' => $tiposContingencia,
        ], 200);


    }
}