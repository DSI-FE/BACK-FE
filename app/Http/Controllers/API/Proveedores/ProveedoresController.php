<?php

namespace App\Http\Controllers\API\Proveedores;
use App\Models\Proveedores\ProveedoresModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class ProveedoresController extends Controller
{
    //
    public function listaProveedores()
    {
        $proveedores = ProveedoresModel::select(['proveedores.codigo', 'proveedores.nrc', 'proveedores.nombre', 'tipo_proveedor.tipo', 'proveedores.nit', 'proveedores.serie'])
       ->join('tipo_proveedor', 'proveedores.tipo', '=', 'tipo_proveedor.idTipo')
        ->get();
        return response()->json($proveedores, 200);

    } 

    public function Proveedor(int $codigo)
    {
        $proveedores = ProveedoresModel::select(['proveedores.codigo', 'proveedores.nrc', 'proveedores.nombre', 'tipo_proveedor.tipo', 'proveedores.nit', 'proveedores.serie'])
       ->join('tipo_proveedor', 'proveedores.tipo', '=', 'tipo_proveedor.idTipo')
       ->where('codigo', '=', $codigo)
       ->get();
        return response()->json($proveedores, 200);

    } 

    
    public function insertProveedor(Request $request)
    {
        try {

            $validatedData = Validator::make(
                ['codigo' => ['required', 'integer', 'exists:proveedores,codigo']],
                [
                    'codigo.required' => 'Falta identificador de Proveedor.',
                    'codigo.integer' => 'Identificador de Proveedor no válido.',
                    'codigo.exists' => 'El proveedor solicitado no existe.',
                ]
            )->validate();

            $rules = [
                'codigo' => ['required', 'integer'],
                'nrc' => ['required', 'string', 'max:45'],
                'nombre' => ['required', 'string', 'max:100'],
                'tipo' => ['required', 'integer'],
                'nit' => ['required', 'string', 'max:20'],
            ];

            $messages = [
                'codigo.required' => 'El campo código es obligatorio.',
                'nrc.required' => 'El campo NRC es obligatorio.',
                'nombre.required' => 'El campo nombre es obligatorio.',
                'tipo.required' => 'El campo tipo es obligatorio.',
                'nit.required' => 'El campo NIT es obligatorio.',
            ];

            $request->validate($rules, $messages);

            $newProveedor = [];

            DB::transaction(function () use ($request, &$newProveedor) {

                $newProveedorData =[
                    'codigo' => $request->codigo,
                    'nrc' => $request->nrc,
                    'nombre' => $request->nombre,
                    'tipo' => $request->tipo,
                    'nit' => $request->nit,
                    'serie' => $request->serie
                ];

               $newProveedor = ProveedoresModel::create($newProveedorData);
            });

            return response()->json($newProveedor, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage().$e->getLine()], 500);
        }
    }
   
    

    public function updateProveedor(Request $request, int $codigo)
    {
        try {
            $rules = [
                'nrc' => ['required', 'string', 'max:45'],
                'nombre' => ['required', 'string', 'max:100'],
                'tipo' => ['required', 'integer'],
                'nit' => ['required', 'string', 'max:20'],
                'serie' => ['required', 'string', 'max:100'],
            ];

            $messages = [
                'nrc.required' => 'El campo NRC es obligatorio.',
                'nombre.required' => 'El campo nombre es obligatorio.',
                'tipo.required' => 'El campo tipo es obligatorio.',
                'nit.required' => 'El campo NIT es obligatorio.',
                'serie.required' => 'El campo serie es obligatorio.',
            ];

            $request->validate($rules, $messages);

            $proveedor = ProveedoresModel::where('codigo', $codigo)->firstOrFail();

            DB::transaction(function () use ($request, $proveedor) {
                $proveedor->update([
                    'nrc' => $request->nrc,
                    'nombre' => $request->nombre,
                    'tipo' => $request->tipo,
                    'nit' => $request->nit,
                    'serie' => $request->serie,
                ]);
            });

            return response()->json($proveedor, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->idProveedor . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->idProveedor . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function deleteProveedor(int $codigo)
    {
        try {
            $proveedor = ProveedoresModel::where('codigo', $codigo)->firstOrFail();
            $proveedor->delete();
            return response()->json(['message' => 'Proveedor eliminado'], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() .'message' . $e->getFile() . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Código de proveedor: ' . $codigo);

            // Devolver un mensaje de error en caso de falla
            return response()->json(['message' => 'Error al eliminar el proveedor'], 500);
        }
    }

    
    

    
}
