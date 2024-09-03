<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\Proveedores\Proveedor;

class ProveedoresController extends Controller
{
    //obtener los proveedores
    public function index(){
        $proveedores = Proveedor::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de proveedores',
            'data' => $proveedores,
        ], 200);
    }

}