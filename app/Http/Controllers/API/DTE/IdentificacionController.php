<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\Clientes\Cliente;
use App\Models\DTE\Condicion;
use App\Models\DTE\Identificacion;

class IdentificacionController extends Controller
{
    //obtener los clientes
    public function index(){
        $identificacion = Identificacion::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de tipos de identificacion',
            'data' => $identificacion,
        ], 200);
    }

}