<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\Pivot;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\General\GralFile;
use App\Models\Attendance\Permission;
use App\Models\Attendance\Attachments;


use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentPermissionFile extends Pivot
{
    use HasFactory;

    protected $table = 'att_attachment_att_permission_gral_file';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;
    public $timestamps = false;


    public $fillable = [
        'att_attachment_id',
        'att_permission_id',
        'gral_file_id'
    ];


    protected $casts = [];

    public function file(): BelongsTo
    {
        return $this->belongsTo(GralFile::class, 'gral_file_id','id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'att_permission_id','id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachments::class, 'att_attachment_id','id');
    }

}