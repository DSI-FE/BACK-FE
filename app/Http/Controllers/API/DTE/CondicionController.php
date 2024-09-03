<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\DTE\Condicion;

class CondicionController extends Controller
{
    //obtener las condiciones de la operacion
    public function index(){
        $condicion = Condicion::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de condiciones de operacion',
            'data' => $condicion,
        ], 200);
    }

}