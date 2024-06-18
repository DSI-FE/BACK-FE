<?php

namespace App\Http\Controllers\API\Administration;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;


use App\Models\Attendance\Device;
use App\Models\Attendance\Discount;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Marking;
use App\Models\Attendance\EmployeePermissionType;

use App\Models\Attendance\PermissionType;
use App\Models\Administration\Employee;
use App\Models\Administration\OrganizationalUnit;
use App\Helpers\StringsHelper;

use DatePeriod;
use DateInterval;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use phpseclib3\File\ASN1\Maps\OrganizationName;

class OrganizationalUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $rules = [
                'perPage' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'max:250'],
                'orderBy' => ['nullable', Rule::in(['id', 'name', 'abbreviation'])],
                'orderDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'orderBy.in' => 'Valor de ordenamiento fuera de las opciones aceptables.',
                'orderDirection.in' => 'Valor de dirección de orden fuera de las opciones aceptables.',
            ];

            $request->validate($rules, $messages);

            $perPage = $request->query('perPage', 10);
            $search = $request->query('search', '');
            $orderBy = $request->query('orderBy', 'id');
            $orderDirection = $request->query('orderDirection', 'asc');

            $units = OrganizationalUnit::with(
                            'organizationalUnitType',
                            'organizationalUnitParent',
                            'functionalPositions'
                        )
                        ->where('adm_organizational_units.name', 'like', '%' . $search . '%')
                        ->orWhere('adm_organizational_units.abbreviation', 'like', '%' . $search . '%')
                        ->orderBy($orderBy, $orderDirection)
                        ->paginate($perPage);

            return response()->json([$units, [
                'search' => $request->query('search', ''),
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
            ]]);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud. Error: '.$e->getMessage().' - Linea: '.$e->getLine()], 500);
        }
    }

    public function indexSimple(Request $request)
    {
        return response()->json( OrganizationalUnit::orderBy('name')->get(), 200 );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'max:250', Rule::unique('adm_organizational_units', 'name')->whereNull('deleted_at')],
                'abbreviation' => ['max:250', Rule::unique('adm_organizational_units', 'abbreviation')->whereNull('deleted_at')],
                'adm_organizational_unit_type_id' => ['required', 'integer', 'exists:adm_organizational_unit_types,id'],
                'adm_organizational_unit_id' => ['nullable', 'integer', 'exists:adm_organizational_units,id'],
            ];

            $messages = [
                'name.required|adm_organizational_unit_type_id.required' => 'Falta :attribute de la Unidad Organizativa.',
                'name.max|abbreviation.max' => ':attribute ha excedido la longitud máxima.',
                'name.unique|abbreviation.unique' => ':attribute ya está asignado a un registro existente.',
                'adm_organizational_unit_type_id.integer|adm_organizational_unit_id.integer' => ':attribute es irreconocible.',
                'adm_organizational_unit_type_id.exists|adm_organizational_unit_id.exists' => ':attribute sin concordancia con los registros actuales.',
            ];

            $attributes = [
                'name' => 'el Nombre',
                'abbreviation' => 'la Abreviatura',
                'adm_organizational_unit_type_id' => 'el Identificador del Tipo de Unidad Organizacional',
                'adm_organizational_unit_id' => 'el Identificador de la Unidad Organizacional',
            ];

            $request->validate($rules, $messages, $attributes);

            $organizationalUnitData = [
                'name' => $request->name,
                'abbreviation' => $request->abbreviation,
                'active' => true,
                'adm_organizational_unit_type_id' => $request->adm_organizational_unit_type_id,
                'adm_organizational_unit_id' => $request->adm_organizational_unit_id,
            ];

            $newOrganizationalUnit = OrganizationalUnit::create($organizationalUnitData);

            return response()->json($newOrganizationalUnit, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' en Línea ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required','integer','exists:adm_organizational_units,id']],
                [
                    'id.required' => 'Falta identificador de Unidad Organizacional.',
                    'id.integer' => 'Identificador de Unidad Organizacional irreconocible.',
                    'id.exists' => 'Unidad Organizacional solicitado sin coincidencia.',
                ]
            )->validate();

            $unit = OrganizationalUnit::with(['functionalPositions' => function ($query) {
                $query->where('active', true);
            }])
                ->findOrFail($validatedData['id']);

            /*$unit = OrganizationalUnit::with([
                'organizational_unit_type',
                'organizational_unit_parent',
                'organizational_units',
                'functional_positions' => function ($query) {
                    $query->where('active', true);
                },
            ])->first($validatedData['id']);*/

            return response()->json($unit, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OrganizationalUnit $organizationalUnit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_organizational_units,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Unidad Organizacional',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('adm_organizational_units', 'name')->whereNull('deleted_at')],
                'abbreviation' => ['max:250', Rule::unique('adm_organizational_units', 'abbreviation')->whereNull('deleted_at')],
                'active' => ['required', Rule::in(['true', 'false'])],
                'adm_organizational_unit_type_id' => ['required', 'integer', 'exists:adm_organizational_unit_types,id'],
                'adm_organizational_unit_id' => ['nullable', 'integer', 'exists:adm_organizational_units,id'],
            ];

            $messages = [
                'name.required|adm_organizational_unit_type_id.required' => 'Falta :attribute de la Unidad Organizativa.',
                'name.max|abbreviation.max' => ':attribute ha excedido la longitud máxima.',
                'name.unique|abbreviation.unique' => ':attribute ya está asignado a un registro existente.',
                'active.required' => '',
                'active.in' => '',
                'adm_organizational_unit_type_id.integer|adm_organizational_unit_id.integer' => ':attribute es irreconocible.',
                'adm_organizational_unit_type_id.exists|adm_organizational_unit_id.exists' => ':attribute sin concordancia con los registros actuales.',
            ];

            $attributes = [
                'name' => 'el Nombre',
                'abbreviation' => 'la Abreviatura',
                'active' => 'el Estado',
                'adm_organizational_unit_type_id' => 'el Identificador del Tipo de Unidad Organizacional',
                'adm_organizational_unit_id' => 'el Identificador de la Unidad Organizacional',
            ];

            $request->validate($rules, $messages, $attributes);

            $organizationalUnitData = [
                'name' => $request->name,
                'abbreviation' => $request->abbreviation,
                'active' => true,
                'adm_organizational_unit_type_id' => $request->adm_organizational_unit_type_id,
                'adm_organizational_unit_id' => $request->adm_organizational_unit_id,
            ];

            $updatedOrganizationalUnit = NULL;

            DB::transaction(function () use ($validatedData, $organizationalUnitData, &$updatedOrganizationalUnit) {
                $updatedOrganizationalUnit = OrganizationalUnit::findOrFail($validatedData['id']);
                $updatedOrganizationalUnit->update($organizationalUnitData);
            });

            return response()->json($updatedOrganizationalUnit, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage(). ' | ' . $e->getFile() . ' ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()) . ' | id: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_organizational_units,id']],
                [
                    'id.required' => 'Falta el :attribute.',
                    'id.integer' => 'El :attribute es irreconocible.',
                    'id.exists' => 'El :attribute enviado, sin coincidencia.',
                ],
                [
                    'id' => 'Identificador de Unidad Organizacional',
                ]
            )->validate();

            $organizationalUnit = NULL;

            DB::transaction(function () use ($validatedData, $organizationalUnit) {
                $organizationalUnit = OrganizationalUnit::findOrFail($validatedData['id']);
                $organizationalUnit->delete();
                $organizationalUnit['status'] = 'deleted';
            });

            return response()->json($organizationalUnit, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getChildrens($id)
    {
        $data = [];
        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrens($orgUnitMain->code)->get();
        foreach ($orgUnits as $key => $orgUnit) {
            $data[$key]['id'] = $orgUnit->id;
            $data[$key]['name'] = $orgUnit->name;
        }
        return response()->json($data, 200);
    }

    public function getEmployees($id)
    {
        $data = [];
        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrenAndEmployees($orgUnitMain->code)->get();
        $i=0;

        foreach ($orgUnits as $key => $orgUnit) {
            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositions;
            foreach ($empFunPositions as $keyEmp => $empFunPos) {
                $data[$i]['id'] = $empFunPos->employee->id;
                $data[$i]['name'] = $empFunPos->employee->name;
                $data[$i]['lastname'] = $empFunPos->employee->lastname;
                $data[$i]['functional_position'] = $empFunPos->functionalPosition->name;
                $i++;
            }
        }
        return response()->json($data, 200);
    }

    public function getEmployeesSimple($id)
    {
        $data = [];
        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::employees($orgUnitMain->code)->get();
        $i=0;

        foreach ($orgUnits as $key => $orgUnit) {
            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositions;
            foreach ($empFunPositions as $keyEmp => $empFunPos) {
                $data[$i]['id'] = $empFunPos->employee->id;
                $data[$i]['name'] = $empFunPos->employee->name;
                $data[$i]['lastname'] = $empFunPos->employee->lastname;
                $data[$i]['functional_position'] = $empFunPos->functionalPosition->name;
                $i++;
            }
        }
        return response()->json($data, 200);
    }

    public function getBossEmployees($id)
    {
        $data = [];
        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrenAndBossEmployees($orgUnitMain->code)->get();
        $i=0;
        foreach ($orgUnits as $key => $orgUnit) {
            $empFunPositions = $orgUnit->activeBossEmployeePrincipalFunctionalPositions;
            foreach ($empFunPositions as $keyEmp => $empFunPos) {
                $data[$i]['id'] = $empFunPos->employee->id;
                $data[$i]['name'] = $empFunPos->employee->name;
                $data[$i]['lastname'] = $empFunPos->employee->lastname;
                $data[$i]['functional_position'] = $empFunPos->functionalPosition->name;
                $i++;
            }
        }
        return response()->json($data, 200);
    }

    public function getEmployeesWithDiscount($id,Request $request)
    {
        $data = [];

        $dateIni = $request['date_ini'] ?? Carbon::now()->startOfMonth();
        $dateEnd = $request['date_end'] ?? Carbon::now()->endOfMonth();
        $showNoLaboralDays= $request['show_no_laboral_days']==='true' ? true : false;
        $period = CarbonPeriod::create($dateIni,$dateEnd);

        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrenAndEmployeesMarkingRequired($orgUnitMain->code)->get();
        $i=0;
        
        foreach ($orgUnits as $key => $orgUnit) {

            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositionsAndMarkingRequired;

            foreach ($empFunPositions as $keyEmp => $empFunPos) {

                $data2=[];
                try {

                    $employeeId =  $empFunPos->employee->id;
                    $j = 0;
        
                    $totalTimeNotWorked=0;
                    $totalTimeJustifiedPay=0;
                    $totalTimeJustifiedNoPay=0;
                    $totalTimeDiscounted=0;
                    $totalDiscountMount=0;
        
                    $data2['dates']=[];
        
                    foreach ($period as $key => $value)
                    {
        
                        $pasa = false;
                        $date = $value->format('Y-m-d');
                        $dateHoliday = Holiday::actual($date)->first();
                        $isHoliday = $dateHoliday ? true : false;
                        $isWeekend = Carbon::parse($date)->isWeekend();
                        $isToday = Carbon::parse($date)->isToday();
                        $iniMark = Marking::byDateEmployeeType($date,$employeeId,1)->first();
                        $endMark = Marking::byDateEmployeeType($date,$employeeId,2)->first();
                        $discount = Discount::byDateEmployee($date,$employeeId)->first();
        
                        if( (($isHoliday || $isWeekend) && $showNoLaboralDays) || (!$isHoliday && !$isWeekend) ) {
                            $pasa = true;
                        }
        
                        if($discount) {
                            $totalTimeNotWorked+=floatval($discount['time_not_worked']);
                            $totalTimeJustifiedPay+=floatval($discount['time_justified_pay']);
                            $totalTimeJustifiedNoPay+=floatval($discount['time_justified_no_pay']);
                            $totalTimeDiscounted+=floatval($discount['time_discounted']);
                            $totalDiscountMount+=floatval($discount['discount']);
                        }
        
                        if($pasa) {
                            $data2['dates'][$j]['date'] = $date;
                            $data2['dates'][$j]['isWeekend'] = $isWeekend;
                            $data2['dates'][$j]['isHoliday'] = $isHoliday;
                            $data2['dates'][$j]['isToday'] = $isToday;
                            $data2['dates'][$j]['dateHoliday'] = $dateHoliday;
                            $data2['dates'][$j]['iniMark'] = $iniMark;
                            $data2['dates'][$j]['endMark'] = $endMark;
                            $data2['dates'][$j]['discount'] = $discount;
                            $j++;
                        }
        
                    }
                    $data2['totalTimeNotWorked']=$totalTimeNotWorked;
                    $data2['totalTimeJustifiedPay']=$totalTimeJustifiedPay;
                    $data2['totalTimeJustifiedNoPay']=$totalTimeJustifiedNoPay;
                    $data2['totalTimeDiscounted']=$totalTimeDiscounted;
                    $data2['totalDiscountMount']=$totalDiscountMount;
                    
        
                } catch (Exception $e) {
        
                    Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
                    return response()->json(
                    [
                        'message' => 'Ha ocurrido un error al procesar la solicitud.',
                        'errors'=>$e->getMessage()
                    ], 500);
                    
                }

                $photo = null;
            
                try {
                    $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($empFunPos->employee->photo_route_sm)));
                } catch (\Throwable $th) {}


                $data[$i]['id'] = $empFunPos->employee->id;
                $data[$i]['name'] = $empFunPos->employee->name;
                $data[$i]['lastname'] = $empFunPos->employee->lastname;
                $data[$i]['phone'] = $empFunPos->employee->phone;
                $data[$i]['email'] = $empFunPos->employee->email;
                $data[$i]['photo'] = $photo;
                $data[$i]['functional_position'] = $empFunPos->functionalPosition->name;
                $data[$i]['organizational_unit'] = $orgUnit->name;
                
                $data[$i]['discounts'] = $data2;
                $i++;
            }
        }
        return response()->json($data, 200);
    }

    public function getEmployeesPermissionTypes($id,Request $request)
    {
        $data = [];

        $orgUnitMain = OrganizationalUnit::findOrFail($id);
        $orgUnits = OrganizationalUnit::childrenAndEmployees($orgUnitMain->code)->get();
        $i=0;
        
        
        foreach ($orgUnits as $key => $orgUnit) {

            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositions;

            foreach ($empFunPositions as $keyEmp => $empFunPos) {

                $permTypes=[];
                try {

                    $employeeId =  $empFunPos->employee->id;
                    $permissionTypes = Employee::findOrFail($employeeId)->permissionTypes;
                    foreach ($permissionTypes as $keyPer => $perType) {
                        $permTypes[$keyPer]['id'] = $perType->id;
                        $permTypes[$keyPer]['name'] = $perType->name;

                        $permTypes[$keyPer]['max_hours_per_year'] = $perType->max_hours_per_year;
                        $permTypes[$keyPer]['max_hours_per_month'] = $perType->max_hours_per_month;
                        $permTypes[$keyPer]['max_requests_per_year'] = $perType->max_requests_per_year;
                        $permTypes[$keyPer]['max_requests_per_month'] = $perType->max_requests_per_month;

                        $permTypes[$keyPer]['used_hours_on_year'] = $perType->employeePermissionType?->used_hours_on_year;
                        $permTypes[$keyPer]['used_hours_on_month'] = $perType->employeePermissionType?->used_hours_on_month;
                        $permTypes[$keyPer]['used_requests_on_year'] = $perType->employeePermissionType?->used_requests_on_year;
                        $permTypes[$keyPer]['used_requests_on_month'] = $perType->employeePermissionType?->used_requests_on_month;
                    }
                    // $permTypes[] = $employee;
        
                } catch (Exception $e) {
        
                    Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
                    return response()->json(
                    [
                        'message' => 'Ha ocurrido un error al procesar la solicitud.',
                        'errors'=>$e->getMessage()
                    ], 500);
                    
                }

                $photo = null;
            
                try {
                    $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($empFunPos->employee->photo_route_sm)));
                } catch (\Throwable $th) {}


                $data[$i]['id'] = $empFunPos->employee->id;
                $data[$i]['name'] = $empFunPos->employee->name;
                $data[$i]['lastname'] = $empFunPos->employee->lastname;
                $data[$i]['phone'] = $empFunPos->employee->phone;
                $data[$i]['email'] = $empFunPos->employee->email;
                $data[$i]['photo'] = $photo;
                $data[$i]['functional_position'] = $empFunPos->functionalPosition->name;
                $data[$i]['organizational_unit'] = $orgUnit->name;
                
                $data[$i]['permission_types'] = $permTypes;
                $i++;
            }
        }
        return response()->json($data, 200);
    }
    
    public function activeOrganizationalUnits(int $id = null)
    {
        try {
            $commonQuery = OrganizationalUnit::select('id', 'name')
                ->where('active', true);

            if ($id !== null) {
                $validatedData = Validator::make(
                    ['id' => $id],
                    ['id' => ['required', 'integer', 'exists:adm_organizational_units,id']],
                    [
                        'id.required' => 'Falta el :attribute.',
                        'id.integer' => 'El :attribute es irreconocible.',
                        'id.exists' => 'El :attribute enviado, sin coincidencia.',
                    ],
                    [
                        'id' => 'Identificador de Área Organizacional',
                    ]
                )->validate();

                $organizationalUnits = $commonQuery->with(['organizationalUnitType:id,name', 'organizationalUnitParent', 'functionalPositions' => function ($query) { $query->where('active', true); }])
                    ->findOrFail($validatedData['id']);
            } else {
                $organizationalUnits = $commonQuery->with(['organizationalUnitType:id,name'])
                    ->get();
            }

            return response()->json($organizationalUnits, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | ' . $e->getFile() . ' - ' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
