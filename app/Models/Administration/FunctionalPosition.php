<?php

namespace App\Models\Administration;

use App\Models\Administration\Employee;
use App\Models\Administration\OrganizationalUnit;
use App\Models\Administration\EmployeeFunctionalPosition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FunctionalPosition extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_functional_positions';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'abbreviation',
        'description',
        'amount_required',
        'salary_min',
        'salary_max',
        'boss',
        'boss_hierarchy',
        'original',
        'user_required',
        'active',
        'adm_organizational_unit_id',
        'adm_functional_position_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('functional_positions')
            ->logAll()
            ->logOnlyDirty();
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'adm_organizational_unit_id');
    }

    public function functionalPosition(): BelongsTo
    {
        return $this->belongsTo(FunctionalPosition::class,'adm_functional_position_id','id');
    }

    public function employees(): BelongsToMany
    {
        return  $this->belongsToMany(Employee::class, 'adm_employee_adm_functional_position', 'adm_functional_position_id', 'adm_employee_id')
            ->using(EmployeeFunctionalPosition::class)
            ->withPivot(['date_start', 'date_end', 'principal', 'salary', 'active']);
    }

}