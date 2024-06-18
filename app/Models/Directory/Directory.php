<?php

namespace App\Models\Directory;

use App\Models\Administration\Employee;
use App\Models\Directory\Classification;
use App\Models\Directory\Contact;
use App\Models\General\GralFile;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;



class Directory extends Model
{
    use HasFactory;

    protected $table = 'dir_directories';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    public $fillable = 
    [
        'name',
        'classification_name',
        'description',
        'public',
        'gral_file_id',
        'adm_employee_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at'
    ];

    public function employee(): BelongsTo
    {
        return  $this->belongsTo(Employee::class, 'adm_employee_id','id');
    }

    public function file(): BelongsTo
    {
        return  $this->belongsTo(GralFile::class, 'gral_file_id','id');
    }

    public function contacts(): HasMany
    {
        return  $this->HasMany(Contact::class, 'dir_directory_id', 'id');
    }

    public function classifications(): HasMany
    {
        return  $this->HasMany(Classification::class, 'dir_directory_id', 'id');
    }

}