<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeRequestTypeElement extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_request_type_elements';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'type',
        'adm_request_type_id',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    protected static $recordEvents = [
        'created',
        'deleted',
        'updated',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user_request_type_element')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employeeRequestType()
    {
        return $this->belongsTo(EmployeeRequestType::class);
    }

    public function employeeRequests()
    {
        return $this->belongsToMany(EmployeeRequest::class, 'adm_r_t_element_adm_request', 'adm_request_id', 'adm_request_type_element_id')
            ->withPivot('value_boolean', 'value_string', 'field_name');
    }
}
