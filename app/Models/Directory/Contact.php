<?php

namespace App\Models\Directory;

use App\Models\Directory\Directory;
use App\Models\Directory\Classification;
use App\Models\Directory\ClassificationContact;

use App\Models\General\GralFile;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'dir_contacts';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    public $fillable = 
    [
        'name',
        'lastname',
        'email',
        'phone',
        'mobile',
        'description',
        'notes',
        'active',
        'adm_address_id',
        'gral_file_id',
        'dir_directory_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at'
    ];

    public function directory(): BelongsTo
    {
        return  $this->belongsTo(Directory::class, 'dir_directory_id','id');
    }

    public function file(): BelongsTo
    {
        return  $this->belongsTo(GralFile::class, 'gral_file_id','id');
    }

    public function classifications(): BelongsToMany
    {
        return  $this->belongsToMany(Classification::class, 'dir_classification_dir_contact', 'dir_contact_id', 'dir_classification_id')
        ->using(ClassificationContact::class)
        ->as('classificationContact');
    }

}