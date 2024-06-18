<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Administration\Employee;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Carbon\Carbon;

class Compensatory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_compensatories';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'date',
        'time_start',
        'time_end',
        'description',
        'time_requested',
        'time_approved',
        'time_available',
        'date_expiration',
        'boss_generated',
        'status',
        'adm_employee_id',
        'adm_employee_boss_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];
    
    public function employee()
    {
        return $this->belongsTo(Employee::class,'adm_employee_id','id');
    }

    public function boss()
    {
        return $this->belongsTo(Employee::class,'adm_employee_boss_id','id');
    }

    public function scopeInDate(Builder $query,$date,$timeIni,$timeEnd,$employeeId) {

        $startDateTime = Carbon::parse($date . ' ' . $timeIni);
        $endDateTime = Carbon::parse($date . ' ' . $timeEnd);

        return $query->where('date', $date)
            ->where('adm_employee_id',$employeeId)
            ->where(function ($q) use ($timeIni, $timeEnd) {
                $q->where(function ($q) use ($timeIni, $timeEnd) {
                    $q->where('time_start', '>=', $timeIni)
                    ->where('time_start', '<', $timeEnd);
                })->orWhere(function ($q) use ($timeIni, $timeEnd) {
                    $q->where('time_end', '>', $timeIni)
                    ->where('time_end', '<=', $timeEnd);
                })->orWhere(function ($q) use ($timeIni) {
                    $q->where('time_start', '<=', $timeIni)
                    ->where('time_end', '>', $timeIni);
                });
            });

    }

    public function scopeAvailable(Builder $query,$employeeId,$date=null) {
        $date = $date ? $date : Carbon::now();
        return $query->whereDate('date_expiration','>=',$date)
        ->where('adm_employee_id',$employeeId)
        ->where('status',2)
        ->orderBy('date_expiration','asc');
    }

}