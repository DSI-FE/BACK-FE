<?php

namespace App\Models\Administration;

use Spatie\Activitylog\LogOptions;
use App\Models\Administration\Employee;
use Illuminate\Database\Eloquent\Model;
use App\Models\Administration\ParkingArea;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccessCard extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_access_cards';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'adm_parking_area_id',
        'adm_employee_id',
        'identifier',
        'description',
        'active',
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
            ->useLogName('access_card')
            ->logAll()
            ->logOnlyDirty();
    }

    public function parkingArea()
    {
        return $this->belongsTo(ParkingArea::class, 'adm_parking_area_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'adm_employee_id');
    }
}
