<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance\EmployeePermissionType;
use App\Models\Administration\Employee;
use App\Models\Attendance\Step;
use App\Models\Attendance\PermissionStep;
use App\Models\Attendance\AttachmentPermissionFile;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Support\Carbon;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_permissions';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [
        'date_ini',
        'date_end',
        'time_ini',
        'time_end',
        'description',
        'daily_time',
        'boss_generated',
        'state',
        'adm_employee_att_permission_type_id',
        'boss_approved_at',
        'rrhh_approved_at'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];
    
    public function employeePermissionType()
    {
        return $this->belongsTo(EmployeePermissionType::class,'adm_employee_att_permission_type_id','id');
    }

    public function steps() : BelongsToMany
    {
        return  $this->belongsToMany(Step::class, 'att_permission_att_step', 'att_permission_id', 'att_step_id')
                ->using(PermissionAssStep::class);
    }

    public function permissionComments() : HasMany
    {
        return $this->hasMany(PermissionComment::class, 'att_permission_id','id');
    }

    public function attachmentPermissionFile(): HasMany
    {
        return $this->HasMany(AttachmentPermissionFile::class, 'att_permission_id','id');
    }

}