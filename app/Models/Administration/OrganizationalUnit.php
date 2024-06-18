<?php

namespace App\Models\Administration;

use App\Models\Asistencia\AssStep;
use App\Models\Administration\OrganizationalUnitTypes;
use App\Models\Administration\EmployeeFunctionalPosition;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrganizationalUnit extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_organizational_units';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'abbreviation',
        'code',
        'active',
        'adm_organizational_unit_type_id',
        'adm_organizational_unit_id',
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
            ->useLogName('organizational_unit')
            ->logAll()
            ->logOnlyDirty();
    }

    public function organizationalUnitType()
    {
        return $this->belongsTo(OrganizationalUnitTypes::class, 'adm_organizational_unit_type_id');
    }

    public function organizationalUnits() {
        return $this->hasMany(OrganizationalUnit::class,'adm_organizational_unit_id');
    }

    public function allOrganizationalUnits() {
        return $this->organizationalUnits()->with('allOrganizationalUnits','activeEmployeeFunctionalPositions');
    }

    public function organizationalUnitParent() {
        return $this->belongsTo(OrganizationalUnit::class, 'adm_organizational_unit_id');
    }

    public function allOrganizationalUnitParents() {
        return $this->organizationalUnitParent()->with('allOrganizationalUnitParents.activeEmployeeFunctionalPositions','activeEmployeeFunctionalPositions');
    }

    public function functionalPositions()
    {
        return $this->hasMany(FunctionalPosition::class, 'adm_organizational_unit_id', 'id');
    }

    public function steps() {
        return  $this->belongsToMany(Step::class, 'adm_organizational_unit_att_step', 'adm_organizational_unit_id', 'att_step_id')
                ->withTimestamps();
    }

    public function activeEmployeePrincipalFunctionalPositions(): HasManyThrough {
        return $this->hasManyThrough(EmployeeFunctionalPosition::class,FunctionalPosition::class,'adm_organizational_unit_id','adm_functional_position_id','id','id')
        ->with(['employee','functionalPosition'])
        ->join('adm_employees', 'adm_employee_adm_functional_position.adm_employee_id', '=', 'adm_employees.id')
        ->where('adm_employee_adm_functional_position.active',1)
        ->where('adm_employee_adm_functional_position.principal',1)
        ->orderBy('adm_employees.name', 'asc');
    }

    public function scopeChildrenAndEmployees(Builder $query, $code): void {
        $query->where('code', 'LIKE',  $code . '%' )
        ->with(['activeEmployeePrincipalFunctionalPositions']);
    }

    public function scopeEmployees(Builder $query, $code): void {
        $query->where('code', '=',  $code)
        ->with(['activeEmployeePrincipalFunctionalPositions']);
    }

    public function activeEmployeePrincipalFunctionalPositionsAndMarkingRequired(): HasManyThrough {
        return $this->hasManyThrough(EmployeeFunctionalPosition::class,FunctionalPosition::class,'adm_organizational_unit_id','adm_functional_position_id','id','id')
        ->with(['employee','functionalPosition'])
        ->join('adm_employees', 'adm_employee_adm_functional_position.adm_employee_id', '=', 'adm_employees.id')
        ->orderBy('adm_employees.name', 'asc')
        ->where('adm_employee_adm_functional_position.active',1)
        ->where('adm_employee_adm_functional_position.principal',1)
        ->where('adm_employees.marking_required',1);
    }

    public function scopeChildrenAndEmployeesMarkingRequired(Builder $query, $code): void {
        $query->where('code', 'LIKE',  $code . '%' )
        ->with(['activeEmployeePrincipalFunctionalPositionsAndMarkingRequired']);
    }

    public function scopeChildrens(Builder $query, $code): void {
        $query->where('code', 'LIKE',  $code . '%' );
    }


    public function scopeChildrenAndBossEmployees(Builder $query, $code): void {
        $query->where('code', 'LIKE', $code . '%')
            ->whereRaw('LENGTH(code) = ?', [strlen($code) + 2])
            ->with(['activeBossEmployeePrincipalFunctionalPositions']);
    }

    public function activeBossEmployeePrincipalFunctionalPositions(): HasManyThrough {
        return $this->hasManyThrough(EmployeeFunctionalPosition::class,FunctionalPosition::class,'adm_organizational_unit_id','adm_functional_position_id','id','id')
        ->with(['employee','functionalPosition'])
        ->join('adm_employees', 'adm_employee_adm_functional_position.adm_employee_id', '=', 'adm_employees.id')
        ->where('adm_functional_positions.boss',1)
        ->where('adm_employee_adm_functional_position.active',1)
        ->where('adm_employee_adm_functional_position.principal',1)
        ->orderBy('adm_employees.name', 'asc');
    }

    public function loadChildrensInRecursiveHerarchy()
    {
        $this->organizationalUnits->each(function ($child) {
            $child->loadChildrensInRecursiveHerarchy();
        });
    }

    public function loadChildrensInRecursiveHerarchyWithEmployees()
    {
        $this->load(['functionalPositions.employees']);
        $this->organizationalUnits->each(function ($child) {
            $child->loadChildrensInRecursiveHerarchyWithEmployees();
        });
    }

    public function loadChildrensInRecursiveHerarchyWithEmployeesAndPermissionRequests()
    {
        $employees = collect();

        $this->load([ 'functionalPositions.employees' => function ( $query ) {
            $query->where([
                'adm_employee_adm_functional_position.active' => 1,
                'adm_employee_adm_functional_position.principal' => 1,
                'adm_employees.active' => 1
            ])->with(['permissionRequests']);
        }]);

        $functionalPositionEmployees = $this->functionalPositions->flatMap(function ($functionalPosition) {
            return $functionalPosition->employees;
        });

        $functionalPositionEmployees = $functionalPositionEmployees->map(function ($employee) {
            $employee['organizational_unit_name'] = $this->name;
            $employee['organizational_unit_id'] = $this->id;
            return $employee;
        });

        $employees = $employees->merge($functionalPositionEmployees);

        $this->organizationalUnits->each(function ($child) use (&$employees) {
            $childEmployees = $child->loadChildrensInRecursiveHerarchyWithEmployeesAndPermissionRequests();
            
            $childEmployees = $childEmployees->map(function ($employee) use ($child) {
                $employee['organizational_unit_name'] = $child->name;
                $employee['organizational_unit_id'] = $child->id;
                return $employee;
            });

            $employees = $employees->merge($childEmployees);
        });

        return $employees;
    }

    public function loadPermissionRequestsFromChildrensEmployees()
    {
        $employeesWithPermissionRequests = $this->loadChildrensInRecursiveHerarchyWithEmployeesAndPermissionRequests()
        ->pluck('permissionRequests')
        ->flatten();
        return $employeesWithPermissionRequests;
    }
    
    public function loadRecursiveOrganizationalUnitsPrincipalEmployeesWithPermissionRequests($showInactive = false)
    {
        $employees = collect();
        $this->load(['functionalPositions.employees' => function ($query) use ($showInactive) {
            $query->when(!$showInactive, function ($query) {
                $query->where([
                    'adm_employees.active' => 1,
                    'adm_employee_adm_functional_position.active' => 1,
                ]);
            })
            ->where('adm_employee_adm_functional_position.principal',1)
            ->with('permissionRequests');
        }]);
        $functionalPositionEmployees = $this->functionalPositions->flatMap(function ($functionalPosition) {
            return $functionalPosition->employees;
        });
        $employees = $employees->merge($functionalPositionEmployees);
        $this->organizationalUnits->each(function ($child) use (&$employees, $showInactive) {
            $childEmployees = $child->loadRecursiveOrganizationalUnitsPrincipalEmployeesWithPermissionRequests($showInactive);
            $employees = $employees->merge($childEmployees);
        });
        return $employees;
    }

}