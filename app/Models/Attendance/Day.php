<?php

namespace App\Models\Attendance;

use Spatie\Activitylog\LogOptions;
use App\Models\Attendance\Schedule;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Day extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'att_days';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'number',
        'working_day'
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
            ->useLogName('ass_day')
            ->logAll()
            ->logOnlyDirty();
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'att_day_schedule', 'att_day_id', 'att_schedule_id')
            ->withPivot(['time_start', 'time_end'])
            ->withTimestamps();
    }
}
