<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Attendance\Marking;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_devices';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'name',
        'brand',
        'model',
        'description',
        'ip',
        'com_key',
        'delete_after_get',
        'active'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    /**
     * Get all of the markings for the Device
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function markings(): HasMany
    {
        return $this->hasMany(Marking::class, 'device_id', 'id');
    }

}
