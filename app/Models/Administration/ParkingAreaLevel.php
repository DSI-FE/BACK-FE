<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\ParkingArea;
use App\Models\Administration\Parking;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ParkingAreaLevel extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_parking_area_levels';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'adm_parking_area_id',
        'name',
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
            ->useLogName('parking_area_level')
            ->logAll()
            ->logOnlyDirty();
    }

    public function parkingArea() {
        return $this->belongsTo(ParkingArea::class, 'adm_parking_area_id');
    }

    public function parkings() {
        return $this->hasMany(Parking::class, 'adm_parking_area_level_id');
    }
}
