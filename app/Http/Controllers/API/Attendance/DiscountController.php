<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

use App\Helpers\TimeHelper;

use App\Helpers\StringsHelper;

use App\Models\Attendance\Device;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Marking;
use App\Models\Attendance\Discount;
use App\Models\Attendance\Schedule;
use App\Models\Attendance\Permission;
use App\Models\Attendance\EmployeeCourtesyTime;
use App\Models\Administration\Employee;
use App\Models\General\GralConfiguration;


use Auth;
use DB;
use Datetime;
use Exception;
use DatePeriod;
use DateInterval;
use Carbon\CarbonPeriod;

class DiscountController extends Controller
{

    public function getByDate(Request $request)
    {
        try {
            $data = [];
            $employeeId = $request['employee_id'] ?? Auth::user()->employee->id;
            $date = $request['date'] ? Carbon::createFromFormat('Y-m-d',$request['date']) : Carbon::now();
            $employee = Employee::withActiveSchedule($employeeId,$date)->first();
            $schedule = $employee && count($employee->schedules) > 0 ? $employee->schedules[0] : NULL;
            $daySchedule = $schedule && count($schedule->days) >0 ? $schedule->days[0] : NULL; 
            $permissions = $employee && count($employee->permissions) > 0 ? $employee->permissions : NULL;
            $perms = [];
            if($permissions)
            {
                foreach ($permissions as $permission) {
                    $permission->employeePermissionType->permissionType;
                    switch($permission->state)
                    {
                        case 0: $permission->string_status = ''; break;
                        case 1: $permission->string_status = 'Enviado'; break;
                        case 2: $permission->string_status = 'Aprobado'; break;
                        case 3: $permission->string_status = 'Aprobado'; break;
                        case 4: $permission->string_status = 'Observado'; break;
                        case 5: $permission->string_status = 'Rechazado'; break;
                        case 6: $permission->string_status = 'Cancelado'; break;
                    }
                }
            }
            $holidays = Holiday::actual($date)->get();
            $isHoliday = count($holidays) > 0 ? true : false;
            $isWeekend = Carbon::parse($date)->isWeekend();
            $iniMark = Marking::byDateEmployeeType($date,$employeeId,1)->first();
            $endMark = Marking::byDateEmployeeType($date,$employeeId,2)->first();
            $discount = Discount::byDateEmployee($date,$employeeId)->first();

            $data['date'] = $date;
            $data['isWeekend'] = $isWeekend;
            $data['isHoliday'] = $isHoliday;
            $data['holidays'] = $holidays;
            $data['schedule'] = $schedule;
            $data['daySchedule'] = $daySchedule;
            $data['iniMark'] = $iniMark;
            $data['endMark'] = $endMark;
            $data['discount'] = $discount;
            $data['permissions'] = $permissions;
            return response()->json($data, 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Ha ocurrido un error al proceddddsar la solicitud.','errors'=>$e->getMessage()], 500);
        }
    }

    public function calculatePeriod(Request $request)
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

            foreach ($period as $key => $value) {
                $date = $value->format('Y-m-d');
                $data[$key]['date'] = $date;
                $data[$key]['discounts'] = $this->calculateDate($date,$employees);
            }
            $response = $data;
        }  else {
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

    public function calculateDate($date,$employees=null)
    {
        try {
            $data = [];
            $date = $date ? Carbon::createFromFormat('Y-m-d',$date) : Carbon::now();
            $holidays = Holiday::actual($date)->get();
            if(count($holidays)===0) {

                $employees = $employees ?? Employee::activeMarkingRequired()->get();
                foreach ($employees as $key => $employee) {
                    $employeeId = $employee->id ?? $employee['value'];
                    $data[$key]['discounts'] = $this->calculateDateEmployee($date->format('Y-m-d'),$employeeId);
                }
            }
            return $data;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line:'.$e->getLine()], 500);
        }
    }

    public function calculateDateEmployee($date,$employeeId)
    {
        try {
            $data=[];
            $date = $date ? Carbon::createFromFormat('Y-m-d H:i:s',$date.' 00:00:00') : Carbon::now();
            $employeeId = $employeeId ? $employeeId : Auth::user()->employee->id;

            $employee = Employee::withActiveScheduleAndMarkingRequired( $employeeId, $date )->first();
            $schedule = $employee && count($employee->schedules) > 0 ? $employee->schedules[0] : NULL;
            $daySchedule = $schedule && count($schedule->days) >0 ? $schedule->days[0] : NULL; 
            $permissions = $employee && count($employee->permissions) > 0 ? $employee->permissions : NULL;
            $holidays = Holiday::actual($date)->get();

            if ( $daySchedule ) {
                $discountedTime = 0;
                $notWorkedTime = 0;
                $justifiedTime = 0;
                $justifiedTimeNoPay = 0;
                $discount = 0;
                $salaryPerMin = 0;

                if ( count($holidays) === 0 ) {
                    /* Schedule & Markings */
                    $iniSch = Carbon::createFromFormat('H:i:s',$daySchedule->pivot->time_start)->format('H:i');
                    $endSch = Carbon::createFromFormat('H:i:s',$daySchedule->pivot->time_end)->format('H:i');
                    
                    $laboralTime = 8 * 60;  // Quemaditos porque así me dijeron que era siempre y no diera cabida a nada más.
                    $laboralDays = 30;      // Quemaditos porque así me dijeron que era siempre y no diera cabida a nada más.

                    $iniMark = Marking::byDateEmployeeType($date,$employeeId,1)->first();
                    $endMark = Marking::byDateEmployeeType($date,$employeeId,2)->first();
                    
                    $lateTime = ($iniMark && $iniMark->time_late > 0) ? $iniMark->time_late : 0;
                    $earlTime = ($endMark && $endMark->time_early > 0) ? $endMark->time_early : 0;
                    
                    $discountedTime = (!$iniMark || !$endMark) || ($iniMark && (Carbon::createFromFormat('Y-m-d H:i:s',$iniMark->datetime)->format('H:i') > $endSch) ) || ($endMark && (Carbon::createFromFormat('Y-m-d H:i:s',$endMark->datetime)->format('H:i') < $iniSch) ) ? $laboralTime : $lateTime+$earlTime;
                    $notWorkedTime = (!$iniMark || !$endMark) || ($iniMark && (Carbon::createFromFormat('Y-m-d H:i:s',$iniMark->datetime)->format('H:i') > $endSch) ) || ($endMark && (Carbon::createFromFormat('Y-m-d H:i:s',$endMark->datetime)->format('H:i') < $iniSch) ) ? $laboralTime : $lateTime+$earlTime;

                    $justifiedTime = 0;
                    $justifiedTimeNoPay = 0;

                    $discount = 0;

                    /* Functional Position and Salary */
                    $position = count($employee->functionalPositions) > 0 ? $employee->functionalPositions[0] : NULL;
                    $salary = $position && $position->pivot ? $position->pivot->salary : 0;
                    $salaryPerMin = $laboralTime && ($laboralTime > 0) && ($salary > 0) ? $salary / ($laboralDays * $laboralTime)  : 0;

                    /* Justified Time Calc */
                    
                    if ($iniMark) {
                        if( $lateTime || !$endMark ) {
                            if ($permissions && count($permissions)>0) {
                                $iniMarkTime = Carbon::createFromFormat('H:i:s',Carbon::createFromFormat('Y-m-d H:i:s', $iniMark->datetime)->format('H:i:s'))->format('H:i');
                                $secondsAdded = false;
                                foreach ($permissions as $key => $permission) {
                                    $permissionIni = Carbon::createFromFormat('H:i:s',$permission->time_ini)->format('H:i:s');
                                    $permissionEnd = Carbon::createFromFormat('H:i:s',$permission->time_end)->format('H:i:s');

                                    $permissionDateIni = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_ini.' 00:00:00');
                                    $permissionDateEnd = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_end.' 00:00:00');

                                    $ini1 = $iniSch;
                                    $end1 = $endMark ? $iniMarkTime : $endSch;
                                    $ini2 = $date->eq($permissionDateIni) ? $permissionIni : $iniSch;
                                    $end2 = $date->eq($permissionDateEnd) ? $permissionEnd : $endSch;
                                    
                                    $overlapTime = TimeHelper::getOverlapInMinutes($ini1,$end1,$ini2,$end2);
                                    
                                    if ( $overlapTime >= 0 && $overlapTime != NULL && $permission->state === 3 ) {
                                        $employeePermissionType = $permission->employeePermissionType;
                                        $permissionType = $employeePermissionType->permissionType;
                                        if($permissionType->discount_applies !== 1) {
                                            $justifiedTime += $overlapTime;
                                        } else {
                                            $justifiedTimeNoPay += $overlapTime;
                                        }
                                    }
                                    
                                }
                            }
                            if( $lateTime > 0 && $lateTime < 1 ) {
                                $justifiedTime+=$lateTime;
                            }
                        }
                    }

                    if ( $endMark ) {
                        if ( $earlTime || !$iniMark ) {
                            if ( $permissions && count($permissions)>0 ) {
                                $endMarkTime = Carbon::createFromFormat('H:i:s',Carbon::createFromFormat('Y-m-d H:i:s', $endMark->datetime)->format('H:i:s'))->format('H:i');
                                $secondsAdded = false;
                                foreach ( $permissions as $key => $permission ) {
                                    $permissionIni = Carbon::createFromFormat('H:i:s',$permission->time_ini)->format('H:i');
                                    $permissionEnd = Carbon::createFromFormat('H:i:s',$permission->time_end)->format('H:i');

                                    $permissionDateIni = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_ini.' 00:00:00');
                                    $permissionDateEnd = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_end.' 00:00:00');

                                    $ini1 = $iniMark ? $endMarkTime : $iniSch;
                                    $end1 = $endSch;
                                    $ini2 = $date->eq($permissionDateIni) ? $permissionIni : $iniSch;
                                    $end2 = $date->eq($permissionDateEnd) ? $permissionEnd : $endSch;
                                    $overlapTime = TimeHelper::getOverlapInMinutes($ini1,$end1,$ini2,$end2);

                                    if ( $overlapTime >= 0 && $overlapTime != NULL && $permission->state === 3 ) {
                                        $employeePermissionType = $permission->employeePermissionType;
                                        $permissionType = $employeePermissionType->permissionType;
                                        
                                        if ( $permissionType->discount_applies !== 1 ) {
                                            $justifiedTime += $overlapTime;
                                        } else {
                                            $justifiedTimeNoPay += $overlapTime;
                                        }
                                    }
                                }
                            }
                            if($earlTime>0 && $earlTime<1 ) {
                                $justifiedTime+=$earlTime;
                            }
                        }
                    }

                    if ( !$iniMark && !$endMark ) {
                        if ( $permissions && count($permissions) > 0 ) {
                            $secondsAdded = false;
                            foreach ( $permissions as $key => $permission ) {
                                $permissionIni = Carbon::createFromFormat('H:i:s',$permission->time_ini)->format('H:i:s');
                                $permissionEnd = Carbon::createFromFormat('H:i:s',$permission->time_end)->format('H:i:s');

                                $permissionDateIni = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_ini.' 00:00:00');
                                $permissionDateEnd = Carbon::createFromFormat('Y-m-d H:i:s',$permission->date_end.' 00:00:00');

                                $ini1 = $iniSch;
                                $end1 = $endSch;

                                $ini2 = $date->eq($permissionDateIni) ? $permissionIni : $iniSch;
                                $end2 = $date->eq($permissionDateEnd) ? $permissionEnd : $endSch;

                                $overlapTime = TimeHelper::getOverlapInMinutes($ini1,$end1,$ini2,$end2);

                                if (  $overlapTime && $overlapTime >= 0 && $permission->state === 3 ) {

                                    $employeePermissionType = $permission->employeePermissionType;
                                    $permissionType = $employeePermissionType->permissionType;
                                    
                                    if ( $permissionType->discount_applies !== 1 ) {
                                        $justifiedTime+= $overlapTime;
                                    } else {
                                        $justifiedTimeNoPay += $overlapTime;
                                    }
                                }
                            }
                        }
                    }
                }
                
                $discountedTime = round($discountedTime,2);
                $justifiedTime = round($justifiedTime,2);
                
                /* Discount Calc */
                $discountedTime = $discountedTime-$justifiedTime;
                $discount = $salaryPerMin * floor($discountedTime); // Descuento tomando en cuenta solo el minuto y no segundos

                $existingDiscount = Discount::where([['date',$date->format('Y-m-d')],['adm_employee_id',$employeeId]])->first();

                if($discount > 0 || $justifiedTime > 0 || ( $permissions && count($permissions)>0 ) || $existingDiscount) {
                    DB::transaction(function () use ($date,$employeeId,$discount,$discountedTime,$notWorkedTime,$justifiedTime,$justifiedTimeNoPay) {
                        Discount::updateOrCreate(
                            [ 'date' => $date->format('Y-m-d'), 'adm_employee_id' => $employeeId ],
                            [
                                'discount' => $discount,
                                'time_discounted' => $discountedTime,
                                'time_not_worked' => $notWorkedTime,
                                'time_justified_pay' => $justifiedTime,
                                'time_justified_no_pay' => $justifiedTimeNoPay
                            ]
                        );
                    });
                }

                $data['discount'] = $discount;
                $data['notWorkedTime'] = $notWorkedTime;
                $data['justifiedTime'] = $justifiedTime;
                $data['justifiedTimeNoPay'] = $justifiedTimeNoPay;
                $data['discountedTime'] = $discountedTime;
            }

            return $data;
        }
        catch (\Exception $e)
        {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line:'.$e->getLine()], 500);
        }
    }

    public function recalculateLateTime( $iniMark ) {

        $lateTime = $iniMark->time_late;
        $month = Carbon::createFromFormat('Y-m-d H:i:s',$iniMark->datetime)->format('m');
        $month = (int)$month;

        DB::transaction( function () use ( $iniMark, &$lateTime, $month ) {

            $employeeCourtesyTime = EmployeeCourtesyTime::where([
                'adm_employee_id'=>$iniMark->adm_employee_id,
                'month'=>$month
            ])->first();

            if ( $iniMark->courtesy_minutes > 0 ) {
                $employeeCourtesyTime->used_minutes -= $iniMark->courtesy_minutes;
                $employeeCourtesyTime->save();
                $iniMark->courtesy_minutes = 0;
                $iniMark->save();
            }
            $totalAvailableMinutes = $employeeCourtesyTime->available_minutes;
            $availableMinutes = $totalAvailableMinutes - $employeeCourtesyTime->used_minutes;
            if ( $availableMinutes > 0 ) {
                $employeeCourtesyTime->used_minutes = $lateTime <= $totalAvailableMinutes ? $lateTime + $employeeCourtesyTime->used_minutes : $totalAvailableMinutes;
                $employeeCourtesyTime->save();
                $iniMark->courtesy_minutes = $lateTime <= $totalAvailableMinutes ? $lateTime : $totalAvailableMinutes;
                $iniMark->save();
                if ( $availableMinutes < $lateTime ) {
                    $lateTime = $lateTime - $availableMinutes;
                } else {
                    $lateTime = 0;
                }
            }
        });
        
        return $lateTime;
    }
}