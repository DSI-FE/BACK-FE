<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance\PermissionType;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\Pivot;

use App\Models\Attendance\AttachementPermissionStep;
use App\Models\Administration\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionComment extends Pivot
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_permission_comments';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'comment',
        'adm_employee_id',
        'att_permission_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function attachments(): BelongsTo
    {
        return  $this->belongsTo(Permission::class, 'att_permission_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return  $this->belongsTo(Employee::class, 'adm_employee_id','id');
    }
}
