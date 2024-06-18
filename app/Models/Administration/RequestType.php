<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RequestType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_request_types';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $fillable = [
        'name'
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
            ->useLogName('request_type')
            ->logAll()
            ->logOnlyDirty();
    }
}
