<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\DTE\DTE;
use App\Models\DTE\Responsable;
use App\Models\DTE\TipoInvalidacion;
use App\Models\Ventas\Venta;


class ResponsablesController extends Controller
{
    //obtener los clientes
    public function index(){
        $responsables = Responsable::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de personas responsable de anular',
            'data' => $responsables,
        ], 200);
    }
}