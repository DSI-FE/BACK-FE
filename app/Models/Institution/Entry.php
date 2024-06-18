<?php

namespace App\Models\Institution;

use App\Models\Administration\Employee;
use App\Models\General\GralFile;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entry extends Model
{
    use HasFactory;

    protected $table = 'ins_entries';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    public $fillable = [
        'name',
        'description',
        'content',
        'url',
        'show_in_carousel',
        'type',
        'subtype',
        'date_start',
        'date_end',
        'active',
        'adm_employee_id',
        'gral_file_id'
    ];

    protected $casts = [];

    public function employee(): BelongsTo
    {
        return  $this->belongsTo(Employee::class, 'adm_employee_id','id');
    }

    public function file(): BelongsTo
    {
        return  $this->belongsTo(GralFile::class, 'gral_file_id','id');
    }

}