<?php

namespace App\Models\Directory;

use App\Models\Directory\Contact;
use App\Models\Directory\Classification;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassificationContact extends Model
{
    use HasFactory;
    
    protected $table = 'dir_classification_dir_contact';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    public $timestamps = false;

    public $fillable =
    [
        'dir_contact_id',
        'dir_classification_id'
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class,'adm_employee_id','id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class, 'att_permission_type_id','id');
    }
}