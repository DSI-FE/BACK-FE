<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeVehicle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_employee_vehicle';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'adm_employee_id',
        'brand',
        'model',
        'year',
        'license_plate'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    protected static $recordEvents = [
        'created',
        'updated',
        'deleted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_vehicle')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'adm_employee_id');
    }
}
