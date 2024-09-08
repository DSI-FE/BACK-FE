<?php

namespace App\Http\Controllers\API\DTE;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\DTE\Condicion;
use App\Models\DTE\TipoDocumento;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventarios\Inventario;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use App\Models\Ventas\DetalleVenta;
use Illuminate\Support\Facades\DB;

class TipoDocumentoController extends Controller
{
    //obtener las condiciones de la operacion
    public function index(){
        // Obtener todas las ventas
        $documento = TipoDocumento::get();

        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'lista de tipo de documento',
            'data' => $documento,
        ], 200);
    }

}