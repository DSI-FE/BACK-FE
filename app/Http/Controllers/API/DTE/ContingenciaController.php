<?php

namespace App\Http\Controllers\API\DTE;

use App\Http\Controllers\Controller;
use App\Models\DTE\Contingencia;
use App\Models\DTE\DTE;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContingenciaController extends Controller
{
    public function index()
    {
        // Obtener las contingencias de la base de datos con sus relaciones y ordenarlas por id descendente
        $contingencias = Contingencia::with('tipoContingencia')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($contingencia) {
            // Devolver el JSON con el estado modificado
            return [
                'id' => $contingencia->id,
                'fechaInicio' => $contingencia->fechaInicio,
                'fechaFin' => $contingencia->fechaFin,
                'horaInicio' => $contingencia->horaInicio,
                'horaFin' => $contingencia->horaFin,
                'tipo_contingencia_id' => $contingencia->tipo_contingencia_id,
                'motivo_contingencia' => $contingencia->motivo_contingencia,
                'estado_contingencia' => $contingencia->estado_contingencia,
                'responsable_id' => $contingencia->responsable_id,
                'estado' => $contingencia->fechaFin == '' ? 'Iniciado' : 'Finalizada',
                'sello_recepcion' => $contingencia->sello_recepcion,
                'codigo_generacion' => $contingencia->codigo_generacion,
                'tipo_contingencia' => [
                'id' => $contingencia->tipoContingencia->id,
                'nombre' => $contingencia->tipoContingencia->nombre
                ],
            ];
            });

        // Devolver la respuesta en formato JSON
        return response()->json([
            'message' => 'Lista de contingencias sin transmitir',
            'data' => $contingencias,
        ], 200);
    }

    //DTE emitidos en contingencia especifica
    public function DTEContingencia($id)
    {
        // Obtener las contingencias de la base de datos con sus relaciones
        $contingencia = Contingencia::with('tipoContingencia')
            ->where('id', $id)
            ->first();

        if (!$contingencia) {
            return response()->json([
                'message' => 'Contingencia no encontrada',
            ], 404);
        }


        // Obtener los DTE asociados a la contingencia
        $dtes = DTE::with('ventas', 'tipo')->where('contingencia_id', $contingencia->id)
            ->get()
            ->map(function ($dte) {
                return [
                    'id' => $dte->id,
                    'fecha_emision' => $dte->fecha,
                    'codigo_generacion' => $dte->codigo_generacion,
                    'sello_recepcion' => $dte->sello_recepcion,
                    'tipo_dte' => $dte->tipo->nombre,
                    'monto_total' => $dte->ventas->total_pagar,

                ];
            });

        // Devolver la respuesta en formato JSON
        return response()->json([
            'message' => 'DTEs emitidos en la contingencia',
            'data' => $dtes,
        ], 200);
    }

    public function IniciarContingencia(Request $request)
{
    // Validar los datos enviados por el cliente
    $validator = Validator::make($request->all(), [
        'tipo_contingencia_id' => 'required',
        'responsable_id' => 'required',
    ]);

    // Verificar si hay errores en la validación
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 400); // Código de estado 400 para solicitud incorrecta
    }
    // Verificar si hay alguna contingencia activa sin fechaFin
    $contingenciasSinFechaFin = Contingencia::whereNull('fechaFin')->get();

    if ($contingenciasSinFechaFin->isNotEmpty()) {
        return response()->json([
            'message' => 'No se puede iniciar una nueva contingencia mientras haya contingencias activas.',
        ], 400); // Código de estado 400 para solicitud incorrecta
    }

    // Crear una nueva contingencia
    $contingencia = Contingencia::create([
        'fechaInicio' => now()->format('Y-m-d'),
        'fechaFin' => $request->fecha_fin,
        'horaInicio' => now()->format('H:i:s'),
        'horaFin' => $request->horaFin,
        'tipo_contingencia_id' => $request->tipo_contingencia_id,
        'motivo_contingencia' => $request->motivo_contingencia,
        'estado_contingencia' => 1,
        'responsable_id' => $request->responsable_id,
    ]);

    // Devolver la respuesta en formato JSON con un mensaje y los datos
    return response()->json([
        'message' => 'Contingencia iniciada',
        'data' => $contingencia,
    ], 200);
}


    // Finalizar una contingencia
    public function finalizarContingencia(Request $request, $id)
    {
        // Buscar la contingencia en la base de datos
        $contingencia = Contingencia::find($id);
    
        if (!$contingencia) {
            return response()->json([
                'message' => 'Contingencia no encontrada',
            ], 404);
        }
    
        // Actualizar el estado de la contingencia
        $contingencia->update([
            'fechaFin' => now()->format('Y-m-d'),
            'horaFin' => now()->format('H:i:s'),
        ]);
    
        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Contingencia finalizada',
            'data' => $contingencia,
        ], 200);
    }

    //Funcion para verificar si estamos en contingencia y al avez obtener el id de la contingencia que tenemos activa
    public function verificarContingencia()
    {
        // Verificar si hay alguna contingencia activa sin fechaFin
        $contingencia = Contingencia::whereNull('fechaFin')->first();

        if (!$contingencia) {
            return response()->json([
                'message' => 'No hay contingencia activa',
            ], 404);
        }

        // Devolver la respuesta en formato JSON
        return response()->json([
            'message' => 'Contingencia activa',
            'data' => $contingencia,
        ], 200);
    }
    

    //Funcion para transmitir DTEs en contingencia esta en DTEController
}
