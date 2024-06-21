<?php

namespace App\Http\Controllers\API\Clientes;
use App\Http\Controllers\Controller;
use App\Models\Clientes\ActividadEconomica;
use Illuminate\Http\Request;

class ActividadEconomicaController extends Controller
{
    public function index()
    {
        // Esto es para obtener todos las actividades
        $actividades = ActividadEconomica::all();

        // Esto es para devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json($actividades, 200);
    }
}
