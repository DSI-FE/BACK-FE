<?php
namespace App\Http\Controllers\API\Productos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventarios\Inventario;
use App\Models\Productos\UnidadMedida;

class ProductosController extends Controller
{
    public function index()
    {
        // Obtener todos los productos agrupados por su nombre
        $productosAgrupados = Inventario::all()
            ->groupBy('producto_id')
            ->map(function ($items) {
                // Obtener el nombre del producto y las unidades de medida asociadas
                $producto = $items->first()->producto;
                $unidades = $items->map(function ($item) {
                    return [
                        'id' => $item->unidad->id,
                        'nombreUnidad' => $item->unidad->nombreUnidad,
                        'existencias' => $item->existencias,
                        'precioVenta' => $item->precioVenta
                    ];
                });
    
                return [
                    'id' =>$producto->id,
                    'nombreProducto' => $producto->nombreProducto,
                    'unidades' => $unidades
                ];
            })
            ->values();
    
        // Devolver la respuesta en formato JSON con un mensaje y los datos agrupados
        return response()->json([
            'message' => 'Listado de todos los productos',
            'data' => $productosAgrupados,
        ], 200);
    }
    

       //Obtener todos las unidades de medida
       public function show()
       {
           // Obtener todos los productos
           $unidades = UnidadMedida::all();
   
           // Devolver la respuesta en formato JSON con un mensaje y los datos
           return response()->json([
               'message' => 'Lista de todas las unidades de medida',
               'data' => $unidades,
           ], 200);
       }
}
