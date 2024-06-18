<?php

namespace App\Models\Attendance;

use App\Models\Attendance\PermissionType;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionTypeAttachment extends Model
{
    use HasFactory;

    protected $table = 'att_permission_type_attachments';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'mandatory',
        'att_permission_type_id',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
    ];

    public function permissionType()
    {
        return $this->belongsTo(PermissionType::class,'att_permission_type_id','id');
    }
}
