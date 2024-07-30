<?php
namespace App\Http\Controllers\API\Productos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Productos\Producto;


class ProductosController extends Controller
{
       //Obtener todos los productos
       public function index()
       {
           // Obtener todos los productos
           $productos = Producto::all();
   
           // Devolver la respuesta en formato JSON con un mensaje y los datos
           return response()->json([
               'message' => 'Listado de todos los productos',
               'data' => $productos,
           ], 200);
       }
}
