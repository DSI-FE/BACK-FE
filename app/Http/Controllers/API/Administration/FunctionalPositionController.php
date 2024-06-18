<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;

use App\Models\Administration\FunctionalPosition;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use Exception;
use Validator;


class FunctionalPositionController extends Controller
{
    public function index()
    {
        try {
            $data = [];
            $functionalPositions = FunctionalPosition::with(['organizationalUnit','employees'])->orderBy('name')->get();
            foreach ($functionalPositions as $key => $functionalPosition) {
                
                $data[$key]['id'] = $functionalPosition->id;
                $data[$key]['name'] = $functionalPosition->name;
                $data[$key]['boss'] = $functionalPosition->boss;
                $data[$key]['active'] = $functionalPosition->active;
                $data[$key]['organizational_unit'] = $functionalPosition->organizationalUnit?->name;
                $data[$key]['organizational_unit_id'] = $functionalPosition->organizationalUnit?->id;
                $data[$key]['employees'] = [];
                $data[$key]['employees_names'] = '';
                $employees = $functionalPosition->employees;
                $i=0;
                foreach($employees as $keyEmp => $employee) {
                    if($employee?->pivot?->active === 1 && $employee?->pivot?->date_end === null ) {
                        $data[$key]['employees_names'] .= ' '.$employee->name.' '.$employee->lastname;
                        $data[$key]['employees'][$i]['name'] = $employee->name.' '.$employee->lastname;
                        $photo = null;
                        try {
                            $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                        } catch (\Throwable $th) {}
                        $data[$key]['employees'][$i]['photo_image'] = $photo;
                        $i++;
                    }
                }
            }
            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud: '.$e->getMessage()], 500);
        }
    }

    public function indexByOrganizationalUnit($organizationalUnitId)
    {
        try {
            $data = null;
            $functionalPositions = FunctionalPosition::with(['organizationalUnit','employees'])->where( 'adm_organizational_unit_id', $organizationalUnitId )->orderBy('name')->get();
            foreach ($functionalPositions as $key => $functionalPosition) {
                $data[$key]['id'] = $functionalPosition->id;
                $data[$key]['name'] = $functionalPosition->name;
                $data[$key]['boss'] = $functionalPosition->boss;
                $data[$key]['active'] = $functionalPosition->active;
                $data[$key]['organizational_unit'] = $functionalPosition->organizationalUnit?->name;
                $data[$key]['organizational_unit_id'] = $functionalPosition->organizationalUnit?->id;
                $data[$key]['employees'] = [];
                $data[$key]['employees_names'] = '';

                $employees = $functionalPosition->employees;
                $i=0;
                foreach($employees as $keyEmp => $employee) {
                    if($employee?->pivot?->active === 1 && $employee?->pivot?->date_end === null ) {
                        $data[$key]['employees_names'] .= ' '.$employee->name.' '.$employee->lastname;
                        $data[$key]['employees'][$i]['name'] = $employee->name.' '.$employee->lastname;
                        $photo = null;
                        try {
                            $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                        } catch (\Throwable $th) {}
                        $data[$key]['employees'][$i]['photo_image'] = $photo;
                        $i++;
                    }
                }
            }
            return response()->json( $data , 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
        }
    }

    public function store(Request $request)
    {
        $data = [];
        $errors = null;
        $response = null;
        $httpCode = 200;
        $msg = '';
        $validator = $this->storeValidator($request);
        if(!$validator->fails()){

            FunctionalPosition::updateOrCreate(
            [
                'id'=>$request['id']
            ],
            [
                'name'=>$request['name'],
                'abbreviation'=>$request['abbreviation'],
                'description'=>$request['description'],
                'amount_required'=>$request['amount_required'],
                'salary_min'=>$request['salary_min'],
                'salary_max'=>$request['salary_max'],
                'boss'=>$request['boss'],
                'boss_hierarchy'=>$request['boss_hierarchy'],
                'original'=>$request['original'],
                'user_required'=>$request['user_required'],
                'active'=>$request['active'],
                'adm_organizational_unit_id'=>$request['adm_organizational_unit_id'],
                'adm_functional_position_id'=>$request['adm_functional_position_id']
            ]);
            
        } else {
            $errors['message'] = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);
    }

    public function show($id)
    {
        try {

            $data = [];
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required','integer','exists:adm_functional_positions,id']],
                [
                    'id.required' => 'Falta :attribute.',
                    'id.integer' => ':attribute irreconocible.',
                    'id.exists' => ':attribute solicitado sin coincidencia.',
                ],
                [ 'id' => 'Identificador de Cargo Funcional' ]
            )->validate();

            $functionalPosition = FunctionalPosition::findOrFail($validatedData['id']);

            // $functionalPosition->organizational_unit;

            $data['id'] = $functionalPosition->id;
            $data['name'] = $functionalPosition->name;
            $data['abbreviation'] = $functionalPosition->abbreviation;
            $data['description'] = $functionalPosition->description;
            $data['boss'] = $functionalPosition->boss;
            $data['user_required'] = $functionalPosition->user_required;
            $data['active'] = $functionalPosition->active;
            $data['adm_organizational_unit_id'] = $functionalPosition->adm_organizational_unit_id;
            $data['employees'] = [];

            $employees = $functionalPosition->employees;
            $i=0;
            foreach($employees as $keyEmp => $employee) {
                if($employee?->pivot?->active === 1 && $employee?->pivot?->date_end === null ) {
                    $data['employees'][$i]['name'] = $employee->name.' '.$employee->lastname;
                    $photo = null;
                    try {
                        $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                    } catch (\Throwable $th) {}
                    $data['employees'][$i]['photo_image'] = $photo;
                    $i++;
                }
            }


            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' | En Línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }
    
    public function destroy(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_functional_positions,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Posición Funcional',
                ]
            )->validate();

            $functionalPosition = NULL;

            DB::transaction(function () use ($validatedData, &$functionalPosition) {
                $functionalPosition = FunctionalPosition::findOrFail($validatedData['id']);
                $employees = $functionalPosition->employees;
                if(count($employees)==0) {
                    $functionalPosition->delete();
                    $functionalPosition['status'] = 'deleted';
                    return response()->json($functionalPosition, 200);
                } else {
                    throw new Exception('El registro ya cuenta con colaboradores asociados');
                }
            });

            
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeFunctionalPositions(int $id = null)
    {
        try {
            $commonQuery = FunctionalPosition::select('id', 'name')
                ->where('active', true);

            if ($id !== null) {
                $validatedData = Validator::make(
                    ['id' => $id],
                    ['id' => ['required', 'integer', 'exists:adm_functional_positions,id']],
                    [
                        'id.required' => 'Falta el :attribute.',
                        'id.integer' => 'El :attribute es irreconocible.',
                        'id.exists' => 'El :attribute enviado, sin coincidencia.',
                    ],
                    [
                        'id' => 'Identificador de Posición Funcional',
                    ]
                )->validate();
                $functionalPositions = $commonQuery->with(['organizationalUnit:id,name'])->findOrFail($validatedData['id']);
            } else {
                $functionalPositions = $commonQuery->with(['organizationalUnit:id,name'])->get();
            }

            return response()->json($functionalPositions, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'id' => ['integer','exists:adm_functional_positions,id'],
            'name' => ['required','string','max:255'],
            'abbreviation' => ['string','max:255'],
            'description' => ['string'],
            'amount_required' => ['integer'],
            'salary_min' => ['numeric'],
            'salary_max' => ['numeric'],
            'boss' => ['required','boolean'],
            'boss_hierarchy' => ['integer'],
            'original' => ['boolean'],
            'user_required' => ['boolean'],
            'active' => ['required','boolean'],
            'adm_organizational_unit_id' => ['required','integer','exists:adm_organizational_units,id'],
            'adm_functional_position_id' => ['integer','exists:adm_functional_positions,id']
        ];
        $messages = [
            'id.integer' => 'Id debe ser un valor entero',
            'id.exists' => 'Id debe ser un valor existente en los registros',
            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe tener un máximo de 255 caracteres',
            'abbreviation.string' => 'Abreviatura debe ser una cadena de caracteres válida',
            'abbreviation.max' => 'Abreviatura debe tener un máximo de 255 caracteres',
            'description.string' => 'Descripción debe ser una cadena de caracteres válida',
            'amount_required.integer' => 'Cantidad requerida debe ser un valor entero válido',
            'salary_min.numeric' => 'Salario máximo debe ser un valor numérico válido',
            'salary_max.numeric' => 'Salario mínimo debe ser un valor numérico válido',
            'boss.required' => 'Es Jefe debe ser ingresado',
            'boss.boolean' => 'Es Jefe debe ser un valor booleano',
            'boss_hierarchy.integer' => 'Jerarquia de jefe debe ser un valor entero válido',
            'original.boolean' => 'Original debe ser un valor booleano',
            'user_required.boolean' => 'Usuario requerido debe ser un valor booleano',
            'active.required' => 'Activo debe ser ingresado',
            'active.boolean' => 'Activo debe ser un valor booleano',
            'adm_organizational_unit_id.required' => 'Unidad Organizacional debe ser seleccionada',
            'adm_organizational_unit_id.integer' => 'Id de unidad organizacional debe ser un valor entero',
            'adm_organizational_unit_id.exists' => 'Id de unidad organizacional debe ser un valor existente en los registros',
            'adm_functional_position_id.integer' => 'Id de cargo funcional original debe ser un valor entero',
            'adm_functional_position_id.exists' => 'Id de cargo funcional original debe ser un valor existente en los registros'
        ];
        return Validator::make($request->all(),$rules,$messages);
    }
}