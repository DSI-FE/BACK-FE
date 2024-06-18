<?php

namespace App\Models\Administration;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use App\Models\Administration\AccessCard;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\ParkingAreaLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ParkingArea extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_parking_areas';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'description',
        'access_card_required',
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
            ->useLogName('parking_area')
            ->logAll()
            ->logOnlyDirty();
    }

    public function parkingAreaLevels() {
        return $this->hasMany(ParkingAreaLevel::class, 'adm_parking_area_id');
    }

    public function accessCards() {
        return $this->hasMany(AccessCard::class, 'adm_parking_area_id');
    }
}
