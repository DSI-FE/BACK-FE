<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrganizationalUnitTypes extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_organizational_unit_types';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'staff',
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
            ->useLogName('organizational_unit_type')
            ->logAll()
            ->logOnlyDirty();
    }

    public function organizational_units()
    {
        return $this->hasMany(OrganizationalUnit::class, 'adm_organizational_unit_id');
    }
}
