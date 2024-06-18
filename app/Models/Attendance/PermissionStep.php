<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance\PermissionType;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\Pivot;

use App\Models\Attendance\AttachementPermissionStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PermissionStep extends Pivot
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_permission_att_step';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'status',
        'att_step_id',
        'att_permission_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function attachments(): BelongsToMany
    {
        return  $this->belongsToMany(Attachments::class, 'att_attachment_att_permission_att_step', 'att_permission_att_step_id', 'att_attachment_id')
            ->using(AttachementPermissionStep::class);
    }
}
