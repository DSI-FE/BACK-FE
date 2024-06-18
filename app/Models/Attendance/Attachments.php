<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance\StepApprovals;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attachments extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_attachments';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'mandatory',
        'att_step_id',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function step()
    {
        return $this->belongsTo(Step::class,'att_step_id','id');
    }

    public function attachmentPermissionFile(): HasOne
    {
        return $this->HasOne(AttachmentPermissionFile::class, 'att_attachment_id','id');
    }

}