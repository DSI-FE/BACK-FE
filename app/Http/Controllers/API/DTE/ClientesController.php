<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\Clientes\Cliente;
use App\Models\DTE\Condicion;

class ClientesController extends Controller
{
    //obtener los clientes
    public function index(){
        $clientes = Cliente::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de clientes',
            'data' => $clientes,
        ], 200);
    }

}