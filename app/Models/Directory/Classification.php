<?php

namespace App\Models\Directory;

use App\Models\Directory\Directory;
use App\Models\Directory\ClassificationContact;
use App\Models\Directory\Contact;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Classification extends Model
{
    use HasFactory;

    protected $table = 'dir_classifications';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    public $fillable =
    [
        'name',
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

    public function contacts(): BelongsToMany
    {
        return  $this->belongsToMany(Contact::class, 'dir_classification_dir_contact', 'dir_classification_id', 'dir_contact_id')
                ->using(ClassificationContact::class)
                ->as('classificationContact');
    }
}
