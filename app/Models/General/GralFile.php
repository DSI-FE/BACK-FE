<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\HasOne;

class GralFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gral_files';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'original_name',
        'route',
        'status'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function attachmentPermissionFile(): HasOne
    {
        return $this->HasOne(AttachmentPermissionFile::class, 'gral_file_id');
    }
}
