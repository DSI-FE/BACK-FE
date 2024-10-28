<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\DTE\DTE;
use App\Models\DTE\TipoInvalidacion;
use App\Models\Ventas\Venta;


class TipoInvalidacionController extends Controller
{
    //obtener los clientes
    public function index(){
        $invalidaciones = TipoInvalidacion::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de todas las invalidaciones',
            'data' => $invalidaciones,
        ], 200);
    }
}