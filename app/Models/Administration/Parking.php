<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Parking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_parkings';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'adm_parking_area_level_id',
        'adm_employee_id',
        'adm_parking_id',
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
            ->useLogName('parking')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employee() {
        return $this->belongsTo(Employee::class, 'adm_employee_id');
    }

    public function parkingAreaLevel() {
        return $this->belongsTo(ParkingAreaLevel::class, 'adm_parking_area_level_id');
    }

    public function parkingBuddy() {
        return $this->belongsTo(Parking::class, 'adm_parking_id');
    }
}
