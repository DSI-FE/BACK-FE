<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeRequest extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'user_id',
        'employee_id_applicant',
        'employee_id_affected',
        'employee_id_authorizing',
        'adm_request_type_id',
        'status',
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
            ->useLogName('user_request')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employeeRequestType()
    {
        return $this->belongsTo(EmployeeRequestType::class, 'adm_request_type_id');
    }

    public function employeeRequestTypeElements()
    {
        return $this->belongsToMany(EmployeeRequestTypeElement::class, 'adm_r_t_element_adm_request', 'adm_request_id', 'adm_request_type_element_id')
            ->withPivot('value_boolean', 'value_string', 'field_name');
    }
}
