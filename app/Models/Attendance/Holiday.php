<?php

namespace App\Models\Attendance;

use App\Models\General\GralFile;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;

use Spatie\Activitylog\Traits\LogsActivity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Support\Carbon;



class Holiday extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'att_holidays';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'description',
        'type',
        'date_start',
        'time_start',
        'date_end',
        'time_end',
        'permanent',
        'vacation',
        'allow_adjacent_permissions',
        'gral_file_id'
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
            ->useLogName('att_holiday')
            ->logAll()
            ->logOnlyDirty();
    }

    public function scopeActual(Builder $query, $date): void
    {
        $query->whereDate('date_start','<=',$date)->where(function (Builder $query) use ($date)
        {
            return $query->whereDate('date_end','>=',$date)->orWhereNull('date_end');
        });
    }

    public function scopeActualVacation(Builder $query, $date): void
    {
        $query->where('vacation',1)
        ->whereYear('date_start', Carbon::now()->year)
        ->whereDate('date_start','<=',$date)->where(function (Builder $query) use ($date)
        {
            return $query->whereDate('date_end','>=',$date)->orWhereNull('date_end');
        });
    }

    public function scopeBetweenDates(Builder $query, $dateIni, $dateEnd ): void
    {
        $dateIni = Carbon::parse($dateIni);
        $dateEnd = Carbon::parse($dateEnd);
        
        $query->where(function (Builder $query) use ($dateIni, $dateEnd) {
            return $query->whereBetween('date_start', [$dateIni, $dateEnd])
                ->orWhereBetween('date_end', [$dateIni, $dateEnd]);
        })
        ->orWhere(function (Builder $query) use ($dateIni) {
            return $query->where('date_start', '<=', $dateIni)
                  ->where('date_end', '>=', $dateIni);
        });
    }

    public function scopeThisYear(Builder $query): void
    {
        $query->whereYear('date_start', Carbon::now()->year);
    }

    public function file(): BelongsTo
    {
        return  $this->belongsTo(GralFile::class, 'gral_file_id','id');
    }
}
