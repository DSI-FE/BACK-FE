<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\General\GralFile;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachementPermissionStep extends Pivot
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_attachment_att_permission_att_step';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'att_permission_att_step_id',
        'att_attachment_id',
        'gral_file_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function file(): BelongsTo
    {
        return $this->belongsTo(GralFile::class, 'gral_file_id');
    }

}