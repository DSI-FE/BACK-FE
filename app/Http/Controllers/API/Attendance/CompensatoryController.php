<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use App\Models\Administration\Employee;
use App\Models\Administration\OrganizationalUnit;

use App\Models\Attendance\Compensatory;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Marking;

use App\Models\General\GralConfiguration;

use Illuminate\Http\Request;


use Carbon\Carbon;
use Validator;

class CompensatoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = [];
        $errors = null;
        $response = null;
        $httpCode = 200;
        $msg = '';
        $validator = $this->storeValidator($request);
        if(!$validator->fails()){
            $date = Carbon::createFromFormat('Y-m-d',$request['date']);
            if($this->compensatoriesDaysValidator($date)) {
                $laboralTime = $this->laboralTimeValidator($request['adm_employee_id'],$date,$request['time_start'],$request['time_end']);
                $laboralTime = $laboralTime->original;
                if($laboralTime['pass']) {

                    $oldCompensatories = Compensatory::inDate($request['date'],$request['time_start'],$request['time_end'],$request['adm_employee_id'])->get();

                    if ( !count($oldCompensatories)>0 ) {

                        $expirationDate = $this->compensatoriesExpirationDateCalculator($request['date']);
                        Compensatory::updateOrCreate(
                        [
                            'id'=>$request['id']
                        ],
                        [
                            'date'=>$request['date'],
                            'time_start'=>$request['time_start'],
                            'time_end'=>$request['time_end'],
                            'description'=>$request['description'],
                            'time_requested'=>$laboralTime['total_time'],
                            'time_approved'=>0,
                            'time_available'=>0,
                            'date_expiration'=>$expirationDate,
                            'boss_generated'=>0,
                            'status'=>1,
                            'adm_employee_id' => $request['adm_employee_id']
                        ]);
                    } else {
                        $errors['message'] = ['compensatories_old'=>['Ya se han enviado compensatorios en la fecha y horas seleccionadas']];
                        $httpCode   = 400;
                        $response = $errors;
                    }
                } else {
                    $errors['message'] = ['laboral_time'=>[$laboralTime['message']]];
                    $httpCode   = 400;
                    $response = $errors;
                }
            } else {
                $errors['message'] = ['compensatories_days'=>['La fecha seleccionada es anterior al límite permitido']];
                $httpCode   = 400;
                $response = $errors;
            }
        } else {
            $errors['message'] = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = [];
        $compensatory = Compensatory::where('id',$id)->with(['employee'])->first();
        $status = $compensatory->status;
        $statusStr = '';
        switch ($status) {
            case 0: $statusStr = ''; break;
            case 1: $statusStr = 'Enviado'; break;
            case 2: $statusStr = 'Aprobado'; break;
            case 3: $statusStr = 'Aprobado'; break;
            case 4: $statusStr = 'Observado'; break;
            case 5: $statusStr = 'Rechazado'; break;
            case 6: $statusStr = 'Cancelado'; break;
            default: $statusStr = 'Sin Definir'; break;
        }

        $employeeRaw = Employee::with([
            'user',
            'gender',
            'maritalStatus',
            'address',
            'functionalPositions' => function ($query) {
                $query->with('organizationalUnit');
            }
        ])->findOrFail($compensatory->employee->id);

        $functionalPosition = $employeeRaw->functionalPositions->first();
        $organizationalUnit = $functionalPosition ? $functionalPosition->organizationalUnit : null;
        $photo = null;
        
        try {
            $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employeeRaw->photo_route)));
        } catch (\Throwable $th) {}

        $employee = [
            'id' => $employeeRaw->id,
            'name' => $employeeRaw->name,
            'lastname' => $employeeRaw->lastname,
            'photo' => $photo,
            'phone' => $employeeRaw->phone,
            'email' => $employeeRaw->email,
            'functional_position' => $functionalPosition ? $functionalPosition->name : null,
            'organizational_unit' => $organizationalUnit ? $organizationalUnit->name : null,
            'organizational_unit_id' => $organizationalUnit ? $organizationalUnit->id : null
        ];

        $data['id'] = $compensatory->id;
        $data['employee'] = $employee;
        $data['date'] = $compensatory->date;
        $data['time_start'] = $compensatory->time_start;
        $data['time_end'] = $compensatory->time_end;
        $data['description'] = $compensatory->description;
        $data['time_requested'] = $compensatory->time_requested;
        $data['time_approved'] = $compensatory->time_approved;
        $data['time_available'] = $compensatory->time_available;
        $data['date_expiration'] = $compensatory->date_expiration;

        $data['status'] = $compensatory->status;
        $data['status_str'] = $statusStr;

        return response()->json($data,200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function indexByEmployee($employeeId)
    {
        $data = [];
        $employee = Employee::findOrFail($employeeId);
        $compensatoriesRaw = $employee->compensatories;
        $i=0;
        foreach ($compensatoriesRaw as $key => $compensatory) {
            $status = $compensatory->status;
            $statusStr = '';
            switch ($status) {
                case 0: $statusStr = ''; break;
                case 1: $statusStr = 'Enviado'; break;
                case 2: $statusStr = 'Aprobado (Jefe Inmediato)'; break;
                // case 3: $statusStr = 'Aprobado (Talento Humano)'; break;
                case 4: $statusStr = 'Observado'; break;
                case 5: $statusStr = 'Rechazado'; break;
                case 6: $statusStr = 'Cancelado'; break;
                default: $statusStr = 'Sin Definir'; break;
            }
            $data[$i]['employee_id'] = $employee->id;
            $data[$i]['name'] = $employee->name;
            $data[$i]['lastname'] = $employee->lastname;
            $data[$i]['compensatory_id'] = $compensatory->id;
            $data[$i]['description'] = $compensatory->description;
            $data[$i]['status'] = $status;
            $data[$i]['statusStr'] = $statusStr;
            $data[$i]['date'] = $compensatory->date;
            $data[$i]['time_start'] = $compensatory->time_start;
            $data[$i]['time_end'] = $compensatory->time_end;
            $data[$i]['time_requested'] = $compensatory->time_requested;
            $data[$i]['time_approved'] = $compensatory->time_approved;
            $data[$i]['time_available'] = $compensatory->time_available;
            $data[$i]['date_expiration'] = $compensatory->date_expiration;
            $data[$i]['status'] = $status;
            $data[$i]['status_str'] = $statusStr;
            $i++;
        }
        return response()->json($data, 200);
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'date' => ['required','date_format:Y-m-d'],
            'time_start' => ['required','date_format:H:i'],
            'time_end' => ['required','date_format:H:i','after:time_start'],
            'description' => [ 'required'],
            'boss_generated' => ['boolean'],
            'status' => ['integer'],
            'adm_employee_id' => ['required','integer','exists:adm_employees,id'],
            'adm_employee_boss_id' => ['integer','exists:adm_employees,id']
        ];

        $messages = [
            
            'date.required' => 'Fecha debe ser ingresada',
            'date.date_format' => 'Fecha debe cumplir con el formato Y-m-d',
            'time_start.required' => 'Hora inicial debe ser ingresada',
            'time_start.date_format' => 'Hora inicial debe cumplir con el formato H:i',
            'time_end.required' => 'Hora final debe ser ingresada',
            'time_end.date_format' => 'Hora final debe cumplir con el formato H:i',
            'time_end.after' => 'Hora final debe ser mayor a la hora inicial',
            'description.required' => 'Descripción debe ser ingresada',
            'boss_generated.boolean' => 'Generado por Jefe debe ser un valor booleano',
            'status.integer' => 'Estado debe ser un valor entero válido',
            'adm_employee_id.required' => 'Valor del empleado debe ser ingresado',
            'adm_employee_id.integer' => 'Valor del empleado debe ser un valor entero',
            'adm_employee_id.exists' => 'Valor del empleado debe existir en los registros',
            'adm_employee_boss_id.integer' => 'Valor del empleado jefe debe ser un valor entero',
            'adm_employee_boss_id.exists' => 'Valor del empleado jefe debe existir en los registros',
        ];
        
        return Validator::make($request->all(),$rules,$messages);
    }

    public function compensatoriesDaysValidator($date)
    {
        $pass=true;
        $compensatoriesDays = GralConfiguration::where('identifier','compensatories_days')->first();
        if($compensatoriesDays && $compensatoriesDays->value>0){
            $dateMin = Carbon::now()->subDays(($compensatoriesDays->value)+1);
            if (!$date->gte($dateMin)) {
                $pass=false;
            }
        }
        return $pass;
    }

    public function compensatoriesExpirationDateCalculator($date)
    {
        $dateMax=null;
        $date = Carbon::createFromFormat('Y-m-d', $date);
        $compensatoriesDays = GralConfiguration::where('identifier','compensatories_days_to_expire')->first();
        if($compensatoriesDays && $compensatoriesDays->value>0){
            $dateMax = $date->addDays(($compensatoriesDays->value));
        }
        return $dateMax;
    }

    public function laboralTimeValidator($employeeId,$date,$timeIni,$timeEnd)
    {
        $pass=true;
        $message='';
        $totalTime = 0;
        $holiday = Holiday::actualVacation($date)->get();
        $timeIni = Carbon::createFromFormat('H:i', $timeIni);
        $timeEnd = Carbon::createFromFormat('H:i', $timeEnd);
        
        if(count($holiday)==0) {
            $employee = Employee::withActiveSchedule($employeeId,$date)->first();
            $schedule = $employee && count($employee->schedules) > 0 ? $employee->schedules[0] : NULL;
            $daySchedule = $schedule && count($schedule->days) >0 ? $schedule->days[0] : NULL;
            if($daySchedule) {
                $rangeIni = Carbon::createFromFormat('H:i:s', $daySchedule->pivot->time_start);
                $rangeEnd = Carbon::createFromFormat('H:i:s', $daySchedule->pivot->time_end);
                if($date->format('Y-m-d')==='2023-09-22') {
                    $rangeEnd = Carbon::createFromFormat('H:i:s', '12:00:00');
                }

                if($this->isTimeRangeInside($timeIni, $timeEnd, $rangeIni, $rangeEnd)){
                    $pass = false;
                    $message='Tiempo solicitado pertenece al tiempo laboral del empleado';
                } else {

                    $firstRangeIni = Carbon::createFromFormat('H:i', '00:00');
                    $firstRangeEnd = Carbon::createFromFormat('H:i:s', $daySchedule->pivot->time_start);
                
                    $secondRangeIni = Carbon::createFromFormat('H:i:s', $daySchedule->pivot->time_end);
                    if($date->format('Y-m-d')==='2023-09-22') {
                        $secondRangeIni = Carbon::createFromFormat('H:i:s', '12:00:00');
                    }
                    $secondRangeEnd = Carbon::createFromFormat('H:i', '23:59');

                    $firstRangeInside = $this->timeRangesOverlap($timeIni, $timeEnd, $firstRangeIni, $firstRangeEnd);
                    $secondRangeInside = $this->timeRangesOverlap($timeIni, $timeEnd, $secondRangeIni, $secondRangeEnd);

                    $markingIni = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'asc')->first();
                    if ( $markingIni) {
                        $markingIni = Carbon::createFromFormat('Y-m-d H:i:s',$markingIni->datetime)->format('H:i');
                        $markingIni = Carbon::createFromFormat('H:i',$markingIni);
                    }
                    $markingEnd = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'desc')->first();
                    if ( $markingEnd ) {
                        $markingEnd = Carbon::createFromFormat('Y-m-d H:i:s',$markingEnd->datetime)->format('H:i');
                        $markingEnd = Carbon::createFromFormat('H:i',$markingEnd);
                    }

                    if ( $markingIni && $markingEnd) {
                        $timeIniVal = $timeIni->gte($markingIni) && $timeIni->lte($firstRangeEnd);
                        $timeEndVal = $timeEnd->gte($secondRangeIni) && $timeEnd->lte($markingEnd);
                        if ( $firstRangeInside>0 && $secondRangeInside>0 ) {
                            if ( !$timeIniVal && !$timeEndVal ) {
                                $pass=false;
                                $message='Marcación de entrada y salida no respaldan tiempo solicitado';
                            } else {
                                if ( $timeIniVal ) {
                                    if ( $timeEndVal ) {
                                        $totalTime = $firstRangeInside + $secondRangeInside;
                                    } else {
                                        $pass=false;
                                        $message='Marcación de salida no respalda tiempo solicitado';
                                    }
                                } else {
                                    $pass=false;
                                    $message='Marcación de entrada no respalda tiempo solicitado';
                                }
                            }

                        } else if( $firstRangeInside>0 ) {
                            if ( $timeIniVal ) {
                                $totalTime = $firstRangeInside;
                            } else {
                                $pass=false;
                                $message='Marcación de entrada no respalda tiempo solicitado';
                            }
                        } else if ( $secondRangeInside>0 ) {
                            if ( $timeEndVal ) {
                                $totalTime = $secondRangeInside;
                            } else {
                                $pass=false;
                                $message='Marcación de salida no respalda tiempo solicitado';
                            }
                        }
                    } else {
                        $pass=false;
                        $message='No existen marcaciones que respalden su compensatorio';

                    }
                }
            } else {
                $markingIni = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'asc')->first();
                $markingEnd = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'desc')->first();
                if ( $markingIni && $markingEnd && ($markingIni->id !== $markingEnd->id) ) {
                    $totalTime = $timeIni->diffInMinutes($timeEnd);
                } else {
                    $pass=false;
                    $message='No existen marcaciones que respalden su compensatorio';
                }
            }
        } else {
            $markingIni = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'asc')->first();
            $markingEnd = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'desc')->first();
            if ( $markingIni && $markingEnd && ($markingIni->id !== $markingEnd->id) ) {
                $totalTime = $timeIni->diffInMinutes($timeEnd);
            } else {
                $pass=false;
                $message='No existen marcaciones que respalden su compensatorio';
            }
        }
        return response()->json(['pass'=>$pass,'total_time'=>$totalTime,'message'=>$message], 200);
    }

    public function isTimeRangeInside(Carbon $timeIni, Carbon $timeEnd, Carbon $rangeIni, Carbon $rangeEnd): bool {
        return $timeIni->greaterThanOrEqualTo($rangeIni) && $timeEnd->lessThanOrEqualTo($rangeEnd);
    }

    public function timeRangesOverlap($aStart, $aEnd, $bStart, $bEnd) {
        if ($aStart->lt($bEnd) && $bStart->lt($aEnd)) {
            $overlapStart = $aStart->max($bStart);
            $overlapEnd = $aEnd->min($bEnd);
            return $overlapEnd->diffInMinutes($overlapStart);
        }
        return 0;
    }

    public function indexByOrganizationalUnit($organizationalUnitId)
    {
        $data = [];
        $orgUnitMain = OrganizationalUnit::findOrFail($organizationalUnitId);
        $orgUnits = OrganizationalUnit::employees($orgUnitMain->code)->get();
        $i=0;

        foreach ($orgUnits as $key => $orgUnit) {
            $empFunPositions = $orgUnit->activeEmployeePrincipalFunctionalPositions;
            foreach ($empFunPositions as $keyEmp => $empFunPos) {
                $employee = $empFunPos->employee;
                $compensatoriesRaw = Employee::find($employee->id)->compensatories;

                foreach ($compensatoriesRaw as $key => $compensatory) {
                    $status = $compensatory->status;
                    $statusStr = '';
                    switch ($status) {
                        case 0: $statusStr = ''; break;
                        case 1: $statusStr = 'Enviado'; break;
                        case 2: $statusStr = 'Aprobado (Jefe Inmediato)'; break;
                        // case 3: $statusStr = 'Aprobado (Talento Humano)'; break;
                        case 4: $statusStr = 'Observado'; break;
                        case 5: $statusStr = 'Rechazado'; break;
                        case 6: $statusStr = 'Cancelado'; break;
                        default: $statusStr = 'Sin Definir'; break;
                    }
                    $data[$i]['employee_id'] = $employee->id;
                    $data[$i]['name'] = $employee->name;
                    $data[$i]['lastname'] = $employee->lastname;
                    $data[$i]['compensatory_id'] = $compensatory->id;
                    $data[$i]['description'] = $compensatory->description;
                    $data[$i]['status'] = $status;
                    $data[$i]['statusStr'] = $statusStr;
                    $data[$i]['date'] = $compensatory->date;
                    $data[$i]['time_start'] = $compensatory->time_start;
                    $data[$i]['time_end'] = $compensatory->time_end;
                    $data[$i]['time_requested'] = $compensatory->time_requested;
                    $data[$i]['time_approved'] = $compensatory->time_approved;
                    $data[$i]['time_available'] = $compensatory->time_available;
                    $data[$i]['date_expiration'] = $compensatory->date_expiration;
                    $data[$i]['status'] = $status;
                    $data[$i]['status_str'] = $statusStr;
                    $i++;
                }
                
            }
        }
        return response()->json($data, 200);
    }

    public function indexAll()
    {
        $data = [];
        $employees = Employee::all();
        $i=0;
        foreach ($employees as $keyEmp => $employee) {
            $compensatoriesRaw = Employee::find($employee->id)->compensatories;
            foreach ($compensatoriesRaw as $key => $compensatory) {
                $status = $compensatory->status;
                $statusStr = '';
                switch ($status) {
                    case 0: $statusStr = ''; break;
                    case 1: $statusStr = 'Enviado'; break;
                    case 2: $statusStr = 'Aprobado (Jefe Inmediato)'; break;
                    // case 3: $statusStr = 'Aprobado (Talento Humano)'; break;
                    case 4: $statusStr = 'Observado'; break;
                    case 5: $statusStr = 'Rechazado'; break;
                    case 6: $statusStr = 'Cancelado'; break;
                    default: $statusStr = 'Sin Definir'; break;
                }
                $data[$i]['employee_id'] = $employee->id;
                $data[$i]['name'] = $employee->name;
                $data[$i]['lastname'] = $employee->lastname;
                $data[$i]['compensatory_id'] = $compensatory->id;
                $data[$i]['description'] = $compensatory->description;
                $data[$i]['status'] = $status;
                $data[$i]['statusStr'] = $statusStr;
                $data[$i]['date'] = $compensatory->date;
                $data[$i]['time_start'] = $compensatory->time_start;
                $data[$i]['time_end'] = $compensatory->time_end;
                $i++;
            }
            
        }
        return response()->json($data, 200);
    }

    public function manageState(Request $request)
    {
        $errors = null;
        $httpCode = 200;
        $response = null;

        $state = $request['status'];
        $boss = Employee::findOrFail($request['adm_employee_boss_id']);
        $compensatory = Compensatory::findOrFail($request['id']);
        $timeApproved = $request['time_approved'];
        $timeRequested = $compensatory->time_requested;
        $msg = '';

        if( $timeApproved <= $timeRequested ) {
            if( $timeApproved >0 ) {
                $compensatory->status = $state;
                $compensatory->time_approved = $state != 5 ? $timeApproved : 0;
                $compensatory->time_available = $state != 5 ? $timeApproved : 0;
                $compensatory->save();
            } else {
                if($state == 5) { 
                    $compensatory->status = $state;
                    $compensatory->save();
                } else {
                    $errors['message'] = ['time_requested'=>['El tiempo aprobado debe ser mayor a cero']];
                    $httpCode   = 400;
                    $response = $errors;
                }
            }
        } else {
            $errors['message'] = ['time_requested'=>['El tiempo aprobado es mayor al solicitado']];
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);

    }

    public function getTimeAvailableByEmployee( $employeeId )
    {
        $compensatories = Compensatory::available( $employeeId )->sum('time_available');
        return response()->json($compensatories, 200);
    }
    
}