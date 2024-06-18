<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Validation\ValidationException;

use App\Helpers\StringsHelper;


use App\Models\Attendance\Device;
use App\Models\Attendance\Discount;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Marking;
use App\Models\Attendance\PermissionType;
use App\Models\Attendance\EmployeePermissionType;

use App\Models\Attendance\Permission;
use App\Models\Administration\Employee;

use Datetime;
use Exception;
use Carbon\CarbonPeriod;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use TADPHP\TADFactory;

class MarkingController extends Controller
{
    public function getByPeriod(Request $request)
    {
        try {

            $data=[];

            $dateIni = $request['date_ini'] ?? Carbon::now()->startOfMonth();
            $dateEnd = $request['date_end'] ?? Carbon::now()->endOfMonth();
            $employeeId = $request['employee_id'] ?? Auth::user()->employee->id;
            $showNoLaboralDays= $request['show_no_laboral_days']==='true' ? true : false;
            $period = CarbonPeriod::create($dateIni,$dateEnd);
            $employee = Employee::findOrFail($employeeId);

            $i = 0;
            $totalTimeNotWorked=0;
            $totalTimeJustifiedPay=0;
            $totalTimeJustifiedNoPay=0;
            $totalTimeDiscounted=0;
            $totalDiscountMount=0;

            $data['dates']=[];

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
                
                if( (($isHoliday || $isWeekend) && $showNoLaboralDays) || (!$isHoliday && !$isWeekend) )
                {
                    $pasa = true;
                }

                if($discount)
                {
                    $totalTimeNotWorked+=floatval($discount['time_not_worked']);
                    $totalTimeJustifiedPay+=floatval($discount['time_justified_pay']);
                    $totalTimeJustifiedNoPay+=floatval($discount['time_justified_no_pay']);
                    $totalTimeDiscounted+=floatval($discount['time_discounted']);
                    $totalDiscountMount+=floatval($discount['discount']);
                }

                $employeePermissionTypes = EmployeePermissionType::where([
                    'adm_employee_id'=>$employeeId,
                    'month'=>$value->month
                ])->get();
                $permissions = 0;
                foreach($employeePermissionTypes as $key => $employeePermissionType) {
                    $employeePermissionTypePermissions = $employeePermissionType->permissionsInDate($value);
                    if($employeePermissionTypePermissions>0){
                        $permissions+=$employeePermissionTypePermissions;
                    }
                }

                if($pasa)
                {
                    $data['dates'][$i]['date'] = $date;
                    if($permissions>0){
                        $data['dates'][$i]['permissions'] =$permissions;
                    }
                    $data['dates'][$i]['isWeekend'] = $isWeekend;
                    $data['dates'][$i]['isHoliday'] = $isHoliday;
                    $data['dates'][$i]['isToday'] = $isToday;
                    $data['dates'][$i]['dateHoliday'] = $dateHoliday;
                    $data['dates'][$i]['iniMark'] = $iniMark;
                    $data['dates'][$i]['endMark'] = $endMark;
                    $data['dates'][$i]['discount'] = $discount;
                    $i++;
                }

            }

            $employeeRaw = Employee::with([
                'user',
                'gender',
                'maritalStatus',
                'address',
                'functionalPositions' => function ($query) {
                    $query->with('organizationalUnit');
                }
            ])->findOrFail($employeeId);

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


            $data['employee']=$employee;
            $data['totalTimeNotWorked']=$totalTimeNotWorked;
            $data['totalTimeJustifiedPay']=$totalTimeJustifiedPay;
            $data['totalTimeJustifiedNoPay']=$totalTimeJustifiedNoPay;
            $data['totalTimeDiscounted']=$totalTimeDiscounted;
            $data['totalDiscountMount']=$totalDiscountMount;
            
            return response()->json($data, 200);

        } catch (Exception $e) {

            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(
            [
                'message' => 'Ha ocurrido un error al procesar la solicitud.',
                'errors'=>$e->getMessage()
            ], 500);
            
        }
    }

    public function syncDevices(Request $request)
    {
        try
        {
            $data = [];
            $devices = Device::all();
            $employees = Employee::activeMarkingRequired()->get();
            $date = Carbon::now();
            foreach ($devices as $key => $device)
            {
                $tadFactory = new TADFactory(['ip' => $device->ip,'com_key'   => $device->com_key,'encoding'  => 'utf-8']);
                $tad = $tadFactory->get_instance();
                foreach ($employees as $key => $employee)
                {
                    $employeeId = $employee->id;
                    $markingsDv = $this->syncFromDevice($tad,$employeeId,$device->id);
                    // $markings   = $this->setDateEmployeeData($date,$employeeId);
                    $data[$key]['employee_id'] = $employeeId;
                    $data[$key]['markingsDv']  = $markingsDv;
                    // $data[$key]['markings']    = $markings;
                }
            }
            return response()->json($data, 200);
        }
        catch (Exception $e)
        {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(
            [
                'message' => 'Ha ocurrido un error al procesar la solicitud.',
                'errors'=>$e->getMessage()
            ], 500);
        }
    }

    public function syncFromDevice($tad,$employeeId,$deviceId)
    {
        $markings = $tad->get_att_log(['pin' => $employeeId])->to_array();
        if(!empty($markings)) {
            if (isset($markings['Row']['PIN'])){
                Marking::updateOrCreate([
                    'datetime'          => $markings['Row']['DateTime'],
                    'adm_employee_id'   => $employeeId,
                ],[
                    'device_id'         => $deviceId
                ]);
            } else {
                foreach ($markings['Row'] as $marking) {
                    Marking::updateOrCreate([
                        'datetime'          =>  $marking['DateTime'],
                        'adm_employee_id'   => $employeeId,
                    ],[
                        'device_id'         => $deviceId
                    ]);
                }
            }
        }
        return $markings;
    }

    public function setPeriodData(Request $request)
    {
        $data=[];
        $response = null;
        $httpCode = 200;
        $validator = $this->setPeriodDataValidator($request);
        if(!$validator->fails()) {
            $dateIni = $request['date_ini'] ? Carbon::createFromFormat('Y-m-d',$request['date_ini']) : Carbon::now()->startOfMonth();
            $dateEnd = $request['date_end'] ? Carbon::createFromFormat('Y-m-d',$request['date_end']) : $dateIni;
            $period = CarbonPeriod::create($dateIni,$dateEnd);
            $employees = $request['employees'] ?? null;
            foreach ($period as $key => $value)  {
                $date = $value->format('Y-m-d');
                $data[$key]['date'] = $date;
                $data[$key]['markings'] = $this->setDateData($date,$employees);
            }
            $response = $data;
        } else {
            $errors['message'] = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);
    }

    public function setPeriodDataValidator(Request $request)
    {
        $rules = [
            'date_ini' => ['required','date_format:Y-m-d'],
            'date_end' => ['nullable','date_format:Y-m-d','after_or_equal:date_ini'],
        ];

        $messages = [
            'date_ini.required' => 'Fecha inicial debe ser ingresada',
            'date_ini.date_format' => 'Fecha inicial debe cumplir con el formato dd/mm/aaaa',
            'date_end.date_format' => 'Fecha final debe cumplir con el formato dd/mm/aaaa',
            'date_end.after_or_equal' => 'Fecha final debe ser mayor a fecha inicial',
        ];
        
        return Validator::make($request->all(),$rules,$messages);
    }

    public function setDateData($date,$employees = null)
    {
        try
        {
            $data = [];
            $date = $date ? Carbon::createFromFormat('Y-m-d H:i:s',$date.' 00:00:00') : Carbon::now();
            $employees = $employees ?? Employee::activeMarkingRequired()->get();
            foreach ($employees as $key => $employee) {
                $employeeId = $employee->id ?? $employee['value'];
                $data[$key]['employee'] = $employeeId;
                $data[$key]['markings'] = $this->setDateEmployeeData($date->format('Y-m-d'),$employeeId);
            }
            return $data;
        }
        catch (\Exception $e)
        {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number:'.$e->getLine()], 500);
        }
    }
    
    public function setDateEmployeeData($date,$employeeId)
    {
        try
        {
            $date = $date ? Carbon::createFromFormat('Y-m-d H:i:s',$date.' 00:00:00') : Carbon::now();
            $day = $date->format('w');
            $markings = Marking::where('adm_employee_id',$employeeId)->whereDate('datetime',$date)->get();

            if(count($markings)>0)
            {
                $employee = Employee::withActiveSchedule($employeeId,$date)->first();
                $timeIni = $employee->schedules[0]->days[0]->pivot->time_start;
                $timeEnd = $employee->schedules[0]->days[0]->pivot->time_end;
                $timeIni = Carbon::createFromFormat('H:i:s',$timeIni);
                $timeEnd = Carbon::createFromFormat('H:i:s',$timeEnd);
                $timeMid = $timeIni->copy()->addMinutes($timeIni->diffInMinutes($timeEnd) / 2)->format('H:i:s');
                $markings = $this->setMarkingsType($markings,$date,$timeMid);
                foreach($markings as $key => $marking)
                {
                    $marking->update(['time_early' => 0]);
                    $marking->update(['time_late' => 0]);
                    if($marking->type == 1)
                    {
                        $timeDifSchedule = ((strtotime($timeIni) - strtotime(Carbon::createFromFormat('Y-m-d H:i:s',$marking->datetime)->format('H:i'))) / 60 );
                        if ($timeDifSchedule<0)
                        {
                            $marking->update(['time_late' => abs($timeDifSchedule) > (8*60) ? 480 : abs($timeDifSchedule)]);
                        }
                    }
                    elseif ($marking->type == 2)
                    {
                        $timeDifSchedule = ((strtotime($timeEnd) - strtotime(Carbon::createFromFormat('Y-m-d H:i:s',$marking->datetime)->format('H:i'))) / 60 );
                        if ($timeDifSchedule>0)
                        {
                            $marking->update(['time_early' => abs($timeDifSchedule) > (8*60) ? 480 : abs($timeDifSchedule)]);
                        }
                    }
                }
            }
            return $markings;
        }
        catch (\Exception $e)
        {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number:'.$e->getLine()], 500);
        }
    }

    public function setMarkingsType($markings,$date,$timeMid)
    {
        if(count($markings)>1)
        {
            foreach ($markings as $key => $marking) {
                // $marking->type = 0;
                // $marking->save();
                $marking->update(['type'=>0]);
            }
            $markings->toQuery()->update(['type'=>0]);

            $markings->toQuery()
            ->orderBy('datetime','asc')
            ->orderBy('created_at','asc')
            ->orderBy('id','asc')
            ->first()
            ->update(['type'=>1]);

            $markings->toQuery()
            ->orderBy('datetime','desc')
            ->orderBy('created_at','desc')
            ->orderBy('id','desc')
            ->first()
            ->update(['type'=>2]);

        }
        elseif(count($markings)==1)
        {
            $markTime = Carbon::createFromFormat('Y-m-d H:i:s',$markings[0]->datetime)->format('H:s:i');
            $timeEntry = $markTime <= $timeMid ? $markings[0]->update(['type'=>1]) : $markings[0]->update(['type'=>2]);    
        }
        return $markings->fresh();
    }

    public function syncByFiles(Request $request)
    {
        $attFile = $request->file('attendance_file');
        if ($attFile) {
            $linecount = 0;
            $handle = fopen($attFile->getRealPath(), "r");
            
            while (!feof($handle)) {
                $line = fgetcsv($handle, 100, "\t","\t",'\\');
                if ( $line !== FALSE )
                {
                    $empId = filter_var($line[0],FILTER_SANITIZE_NUMBER_INT);
                    $datetime = $line[1];
                    if($empId!='88888'){
                        Marking::updateOrCreate([
                            'datetime'          => $datetime,
                            'adm_employee_id'   => $empId
                        ],[
                            'device_id'         => 1
                        ]);
                    }
                }
                $linecount++;
            }
        }
    }

    public function remoteMark () {
        try {
            $employee = Auth::user()->employee;
            if ($employee && $employee->remote_mark) {
                $data = [
                    'datetime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'adm_employee_id' => Auth::user()->id,
                ];
                $mark = Marking::create($data);
                return response()->json($mark, 200);
            } else {
                throw new Exception('Algo salió mal: Asegurese de contar con los permisos correctos');
            }
            return $employee->remote_mark;
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getMarksFromDevices($id = null) {
        try {
            $query = Device::where('active', true);

            if ($id !== null) {
                $validatedData = Validator::make(
                    ['id' => $id],
                    ['id' => ['required', 'integer', 'exists:att_devices,id']],
                    [
                        'id.required' => 'Falta identificador de Equipo de Marcación.',
                        'id.integer' => 'Identificador de Equipo de Marcación irreconocible.',
                        'id.exists' => 'Equipo de Marcación solicitado sin coincidencia.',
                    ]
                )->validate();

                $query->where('id', $validatedData['id']);
            }

            $devices = $query->get();

            foreach ($devices as $idx => $device) {
                $props = [
                    'ip' => $device->ip,
                    'port' => $device->port,
                    'timeout' => 5,
                    'com_key' => $device->com_key
                ];

                $process = new Process(['python', storage_path('/app/pyzk/getMarks.py'), json_encode($props)]);

                $process->run();

                if ($process->isSuccessful()) {
                    $output = $process->getOutput();

                    $result = json_decode($output, true);

                    foreach ($result['attendance'] as $idx => $mark) {
                        Marking::updateOrCreate([
                            'datetime'          =>  $mark['timestamp'],
                            'adm_employee_id'   => intval($mark['pin']),
                        ],[
                            'device_id'         => $device->id
                        ]);
                    }
                } else {
                    throw new ProcessFailedException($process);
                }
            }
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id ? $id : 'all'));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id ? $id : 'all'));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function marksFromEmployee($employeeId,$date) {
        $date = Carbon::createFromFormat('Y-m-d',$date);
        $data['entry'] = Marking::byDateEmployeeType($date,$employeeId,1)->first();
        $data['leave'] = Marking::byDateEmployeeType($date,$employeeId,2)->first();
        $data['no_type'] = Marking::byDateEmployeeType($date,$employeeId,0)->get();
        return response()->json($data, 200);
    }

    public function markingsByDateEmployee ($date,$employeeId) {
        $data=[];
        $data['ini'] = null;
        $data['end'] = null;
        $date = Carbon::createFromFormat('Y-m-d',$date);
        $employee = Employee::withActiveSchedule($employeeId,$date)->first();
        $entryHour = $employee->schedules[0]->days[0]->pivot->time_start ?? null;
        $leaveHour = $employee->schedules[0]->days[0]->pivot->time_end ?? null;

        $entryHour = $entryHour ? Carbon::createFromFormat('Y-m-d H:i:s','2020-01-02 '.$entryHour) : null;
        $leaveHour = $leaveHour ? Carbon::createFromFormat('Y-m-d H:i:s','2020-01-02 '.$leaveHour) : null;

        $markingIni = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'asc')->first();
        $markingEnd = Marking::byDateEmployee($date,$employeeId)->orderBy('datetime', 'desc')->first();

        if ( $entryHour && $leaveHour ) {
            if (( isset($markingIni) && isset($markingEnd) ) && $markingIni->id !== $markingEnd->id) {
                $data['ini'] = Carbon::createFromFormat('Y-m-d H:i:s',$markingIni->datetime)->format('H:i:s');
                $data['end'] = Carbon::createFromFormat('Y-m-d H:i:s',$markingEnd->datetime)->format('H:i:s');
            } else if (( isset($markingIni) && isset($markingEnd) ) && $markingIni->id === $markingEnd->id) {
                $markingIni = Carbon::createFromFormat('Y-m-d H:i:s',$markingIni->datetime)->format('H:i:s');
                $markingIni = Carbon::createFromFormat('Y-m-d H:i:s','2020-01-02 '.$markingIni);
                $entryType = abs($markingIni->diffInMinutes($entryHour)) < abs($markingIni->diffInMinutes($leaveHour)) ? true : false;
                if($entryType) {
                    $data['ini'] = $markingIni->format('H:i:s');
                } else {
                    $data['end'] = $markingIni->format('H:i:s');
                }
            }
        } else {
            if( isset($markingIni) ) {
                $data['ini'] = Carbon::createFromFormat('Y-m-d H:i:s',$markingIni->datetime)->format('H:i:s');
            }
            if( isset($markingEnd) && ( isset($markingIni) && $markingIni->id !== $markingEnd->id ) ) {
                $data['end'] = Carbon::createFromFormat('Y-m-d H:i:s',$markingEnd->datetime)->format('H:i:s');
            }
        }
        return response()->json($data, 200);
    }

}