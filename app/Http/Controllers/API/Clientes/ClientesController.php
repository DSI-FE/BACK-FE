<?php

namespace App\Http\Controllers\API\Clientes;

use App\Models\Clientes\ClientesModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;


class ClientesController extends Controller
{
    //Obtener todos los clientes
    public function listaclientes()
    {
        $clientes = ClientesModel::select(['clientes.codigo', 'clientes.nombres', 'clientes.apellidos', 'clientes.direccion', 'clientes.telefono', 'clientes.correo'])
            ->get();
        return response()->json($clientes, 200);
    }

    public function listaclientesAll()
    {
        $clientes = ClientesModel::all();
        return response()->json($clientes, 200);
    }


    public function cliente(int $codigo)
    {
        $proveedores = clientesModel::select(['clientes.codigo', 'clientes.nombres', 'clientes.apellidos', 'clientes.direccion', 'clientes.telefono', 'clientes.correo'])
            ->where('codigo', '=', $codigo)
            ->get();
        return response()->json($proveedores, 200);
    }

    public function insertCliente(Request $request)
    {
        try {

            $validatedData = Validator::make(
                ['codigo' => ['required', 'integer', 'exists:clientes,codigo']],
                [
                    'codigo.required' => 'Falta identificador de Cliente.',
                    'codigo.integer' => 'Identificador de Cliente no válido.',
                    'codigo.exists' => 'El Cliente solicitado no existe.',
                ]
            )->validate();

            $rules = [
                'codigo' => ['required', 'integer'],
                'nombres' => ['required', 'string'],
                'apellidos' => ['required', 'string'],
                'tipoDocumento' => ['required', 'integer'],
                'numeroDocumento' => ['required', 'string'],
                'departamento' => ['required', 'integer'],
                'municipio' => ['required', 'integer'],
                'direccion' => ['required', 'string'],
                'actividadEconomica' => ['required', 'integer'],
                'telefono' => ['required', 'string'],
                'correo' => ['required', 'string'],
            ];

            $messages = [
                'codigo.required' => 'El código es obligatorio.',
                'nombres.required' => 'El nombre es obligatorio.',
                'apellidos.required' => 'El apellido es obligatorio.',
                'tipoDocumento.required' => 'El tipo de documento es obligatorio.',
                'numeroDocumento.required' => 'El numeo de documento es obligatorio.',
                'departamento.required' => 'El departamento es obligatorio.',
                'municipio.required' => 'El municipio es obligatorio.',
                'direccion.required' => 'La dirección es obligatoria.',
                'actividadEconomica.required' => 'La actividad económica es obligatoria.',
                'telefono.required' => 'El teléfono es obligatorio.',
                'correo.required' => 'El correo es obligatorio.',
            ];

            $request->validate($rules, $messages);

            $newCliente = [];

            DB::transaction(function () use ($request, &$newCliente) {

                $newClienteData = [
                    'codigo' => $request->codigo,
                    'nombres' => $request->nombres,
                    'apellidos' => $request->apellidos,
                    'tipoDocumento' => $request->tipoDocumento,
                    'numeroDocumento' => $request->numeroDocumento,
                    'departamento' => $request->departamento,
                    'municipio' => $request->municipio,
                    'direccion' => $request->direccion,
                    'actividadEconomica' => $request->actividadEconomica,
                    'telefono' => $request->telefono,
                    'correo' => $request->correo,
                ];

                $newCliente = ClientesModel::create($newClienteData);
            });

            return response()->json($newCliente, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage() . $e->getLine()], 500);
        }
    }

    
    public function deleteCliente(int $codigo)
    {
        try {
            $cliente = ClientesModel::where('codigo', $codigo)->firstOrFail();
            $cliente->delete();
            return response()->json(['message' => 'Cliente eliminado'], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() .'message' . $e->getFile() . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Código de proveedor: ' . $codigo);

            // Devolver un mensaje de error en caso de falla
            return response()->json(['message' => 'Error al eliminar el cliente'], 500);
        }
    }

    public function updateCliente(Request $request, int $codigo)
    {
        try {
            $rules = [
                'nombres' => ['required', 'string', 'max:100'],
                'apellidos' => ['required', 'string', 'max:100'],
                'tipoDocumento' => ['required', 'integer'],
                'numeroDocumento' => ['required', 'string', 'max:20'],
                'departamento' => ['required', 'integer'],
                'municipio' => ['required', 'integer'],
                'direccion' => ['required', 'string', 'max:255'],
                'actividadEconomica' => ['required', 'integer'],
                'telefono' => ['required', 'string', 'max:15'],
                'correo' => ['required', 'string', 'email', 'max:100'],
            ];

            $messages = [
                'nombres.required' => 'El campo nombres es obligatorio.',
                'apellidos.required' => 'El campo apellidos es obligatorio.',
                'tipoDocumento.required' => 'El campo tipo de documento es obligatorio.',
                'numeroDocumento.required' => 'El campo número de documento es obligatorio.',
                'departamento.required' => 'El campo departamento es obligatorio.',
                'municipio.required' => 'El campo municipio es obligatorio.',
                'direccion.required' => 'El campo dirección es obligatorio.',
                'actividadEconomica.required' => 'El campo actividad económica es obligatorio.',
                'telefono.required' => 'El campo teléfono es obligatorio.',
                'correo.required' => 'El campo correo es obligatorio.',
                'correo.email' => 'El campo correo debe ser una dirección de correo válida.',
            ];

            $request->validate($rules, $messages);

            $cliente = ClientesModel::where('codigo', $codigo)->firstOrFail();

            DB::transaction(function () use ($request, $cliente) {
                $cliente->update([
                    'nombres' => $request->nombres,
                    'apellidos' => $request->apellidos,
                    'tipoDocumento' => $request->tipoDocumento,
                    'numeroDocumento' => $request->numeroDocumento,
                    'departamento' => $request->departamento,
                    'municipio' => $request->municipio,
                    'direccion' => $request->direccion,
                    'actividadEconomica' => $request->actividadEconomica,
                    'telefono' => $request->telefono,
                    'correo' => $request->correo,
                ]);
            });

            return response()->json($cliente, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}
