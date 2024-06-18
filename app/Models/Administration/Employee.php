<?php

namespace App\Models\Administration;

use App\Models\User;

use Spatie\Activitylog\LogOptions;

use App\Models\Administration\Gender;
use App\Models\Attendance\EmployeeSchedule;
use App\Models\Attendance\EmployeePermissionType;
use App\Models\Attendance\PermissionComment;
use App\Models\Attendance\Compensatory;
use App\Models\Attendance\EmployeeCourtesyTime;
use App\Models\Attendance\Marking;
use App\Models\Attendance\Permission;
use App\Models\Attendance\PermissionType;
use App\Models\Attendance\PermissionRequest;
use App\Models\Attendance\Schedule;
use App\Models\Attendance\Step;

use App\Models\Administration\Address;

use Illuminate\Database\Eloquent\Model;
use App\Models\Administration\AccessCard;

use App\Models\Directory\Directory;

use Illuminate\Database\Eloquent\Builder;

use App\Models\Administration\DocumentType;

use App\Models\Institution\Entry;

use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Administration\MaritalStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\EmployeeRequest;
use App\Models\Administration\FunctionalPosition;
use App\Models\Administration\PaymentVoucherFile;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Administration\EmployeeFunctionalPosition;
use App\Models\Administration\EmployeeRequestTypeElement;
use App\Models\Request\RequestCategory;
use App\Models\Request\RequestDetail;
use App\Models\Request\RequestResponse;
use App\Models\Reservations\ResourceType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Support\Carbon;

class Employee extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_employees';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'name',
        'lastname',
        'email',
        'email_personal',
        'phone',
        'phone_personal',
        'photo_name',
        'photo_route',
        'photo_route_sm',
        'birthday',
        'marking_required',
        'status',
        'active',
        'user_id',
        'adm_gender_id',
        'adm_marital_status_id',
        'adm_address_id',
        'remote_mark,',
        'external',
        'viatic',
        'children',
        /** */'vehicle',
        /** */'adhonorem',
        /** */'parking',
        /** */'disabled',
        'unsubscribe_justification',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [];

    protected static $recordEvents = [
        'created',
        'updated',
        'deleted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee')
            ->logAll()
            ->logOnlyDirty();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'adm_gender_id');
    }

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class, 'adm_marital_status_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'adm_address_id');
    }

    public function permissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Permission::class,
            EmployeePermissionType::class,
            'adm_employee_id',
            'adm_employee_att_permission_type_id',
            'id',
            'id'
        );
    }

    public function scopeWithPermissionsBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereHas('permissions', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date_ini', [$startDate, $endDate])
                  ->orWhereBetween('date_end', [$startDate, $endDate]);
        });
    }

    public function permissionsBetweenDates($startDate, $endDate)
    {
        return $this->permissions()
                    ->whereHas('employeePermissionType', function ($query) {
                        $query->where('adm_employee_id', $this->id);
                    })
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date_ini', [$startDate, $endDate])
                            ->orWhereBetween('date_end', [$startDate, $endDate]);
                    })
                    ->get();
    }
    
    public function permissionTypes(): BelongsToMany
    {
        return  $this->belongsToMany(PermissionType::class, 'adm_employee_att_permission_type', 'adm_employee_id', 'att_permission_type_id')
            ->using(EmployeePermissionType::class)
            ->withPivot(['used_minutes', 'used_requests', 'month'])
            ->as('employeePermissionType');
    }

    public function steps()
    {
        return  $this->belongsToMany(Step::class, 'adm_employee_att_step', 'att_step_id', 'adm_employee_id');
    }

    public function permissionComments(): HasMany
    {
        return  $this->HasMany(PermissionComment::class, 'att_permission_comments', 'adm_employee_id','id');
    }

    public function markings(): HasMany
    {
        return $this->hasMany(Marking::class, 'adm_employee_id', 'id');
    }

    public function discounts(): hasMany
    {
        return $this->hasMany(Discount::class, 'adm_employee_id', 'id');
    }

    /* public function functionalPositions(): BelongsToMany
    {
        return  $this->belongsToMany(FunctionalPosition::class, 'adm_employee_adm_functional_position', 'adm_employee_id', 'adm_functional_position_id')
            ->using(EmployeeFunctionalPosition::class)
            ->withPivot(['date_start', 'date_end', 'principal', 'salary', 'active']);
    } */

    public function functionalPositions(): BelongsToMany
    {
        return $this->belongsToMany(FunctionalPosition::class, 'adm_employee_adm_functional_position', 'adm_employee_id', 'adm_functional_position_id')
            ->using(EmployeeFunctionalPosition::class)
            ->withPivot(['date_start', 'date_end', 'principal', 'salary', 'active'])
            ->wherePivot('principal', true)
            ->whereNull('date_end')
            //->wherePivot('active', true)
            ->orderby('date_start', 'desc');
    }
    
    public function schedules(): BelongsToMany
    {
        return  $this->belongsToMany(Schedule::class, 'adm_employee_att_schedule', 'adm_employee_id', 'att_schedule_id')
            ->using(EmployeeSchedule::class)
            ->withPivot(['date_start', 'date_end', 'active'])
            ->as('employeeSchedule');
    }

    public function activeSchedule($date=null)
    {
        $date = $date ? Carbon::createFromFormat('Y-m-d',$date) : Carbon::now();
        return $this->schedules()
        ->where('adm_employee_att_schedule.active', 1)
        ->where('adm_employee_att_schedule.date_start', '<=', $date)
        ->where(function ($query) use ($date) {
            $query
                ->where('adm_employee_att_schedule.date_end', '>=', $date)
                ->orWhereNull('adm_employee_att_schedule.date_end');
        })
        ->with(['days' => function ($query) use ($date) {
            $query
                ->where('number', $date->dayOfWeek);
        }])
        ->orderBy('adm_employee_att_schedule.id', 'desc')
        ->first();
    }

    public function scopeActiveMarkingRequired(Builder $query): void
    {
        $query->where('status', 1)->where('active', 1)->where('marking_required', 1);
    }

    public function scopeWithActiveSchedule(Builder $query, $employeeId, $date): void
    {
        $query->where('id', $employeeId)
            ->with(['schedules' => function ($query) use ($date) {
                $query
                    ->where('adm_employee_att_schedule.active', 1)
                    ->where('adm_employee_att_schedule.date_start', '<=', $date)
                    ->where(function ($query) use ($date) {
                        $query
                            ->where('adm_employee_att_schedule.date_end', '>=', $date)
                            ->orWhereNull('adm_employee_att_schedule.date_end');
                    })
                    ->with(['days' => function ($query) use ($date) {
                        $query
                            ->where('number', $date->dayOfWeek);
                    }])
                    ->orderBy('adm_employee_att_schedule.id', 'desc')->first();
            }])
            ->with(['functionalPositions' => function ($query) use ($date) {
                $query
                    ->where('adm_employee_adm_functional_position.active', 1)
                    ->where('adm_employee_adm_functional_position.principal', 1)
                    ->where('adm_employee_adm_functional_position.date_start', '<=', $date)
                    ->where(function ($query) use ($date) {
                        $query
                            ->where('adm_employee_adm_functional_position.date_end', '>=', $date)
                            ->orWhereNull('adm_employee_adm_functional_position.date_end');
                    })
                    ->orderBy('adm_employee_adm_functional_position.id', 'desc')->first();
            }]);
    }

    public function scopeWithActiveScheduleDays(Builder $query, $employeeId, $date): void
    {
        $query->where('id', $employeeId)
            ->with(['schedules' => function ($query) use ($date) {
                $query
                    ->where('adm_employee_att_schedule.active', 1)
                    ->where('adm_employee_att_schedule.date_start', '<=', $date)
                    ->where(function ($query) use ($date) {
                        $query
                            ->where('adm_employee_att_schedule.date_end', '>=', $date)
                            ->orWhereNull('adm_employee_att_schedule.date_end');
                    })
                    ->with(['days'])
                    ->orderBy('adm_employee_att_schedule.id', 'desc')->first();
            }])
            ->with(['functionalPositions' => function ($query) use ($date) {
                $query
                    ->where('adm_employee_adm_functional_position.active', 1)
                    ->where('adm_employee_adm_functional_position.principal', 1)
                    ->where('adm_employee_adm_functional_position.date_start', '<=', $date)
                    ->where(function ($query) use ($date) {
                        $query
                            ->where('adm_employee_adm_functional_position.date_end', '>=', $date)
                            ->orWhereNull('adm_employee_adm_functional_position.date_end');
                    })
                    ->orderBy('adm_employee_adm_functional_position.id', 'desc')->first();
            }]);
    }

    public function scopeWithActiveScheduleAndMarkingRequired(Builder $query, $employeeId, $date): void
    {
        $query->where('id', $employeeId)
        ->where('marking_required',1)
        ->with(['schedules' => function ($query) use ($date) {
            $query
                ->where('adm_employee_att_schedule.active', 1)
                ->where('adm_employee_att_schedule.date_start', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query
                        ->where('adm_employee_att_schedule.date_end', '>=', $date)
                        ->orWhereNull('adm_employee_att_schedule.date_end');
                })
                ->with(['days' => function ($query) use ($date) {
                    $query
                        ->where('number', $date->dayOfWeek);
                }])
                ->orderBy('adm_employee_att_schedule.id', 'desc')->first();
        }])
        ->with(['functionalPositions' => function ($query) use ($date) {
            $query
                ->where('adm_employee_adm_functional_position.active', 1)
                ->where('adm_employee_adm_functional_position.principal', 1)
                ->where('adm_employee_adm_functional_position.date_start', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query
                        ->where('adm_employee_adm_functional_position.date_end', '>=', $date)
                        ->orWhereNull('adm_employee_adm_functional_position.date_end');
                })
                ->orderBy('adm_employee_adm_functional_position.id', 'desc')->first();
        }])
        ->with(['permissions' => function ($query) use ($date) {
            $query
            ->where('att_permissions.date_ini', '<=', $date->format('Y-m-d'))
            ->where(function ($query) use ($date) {
                $query
                    ->where('att_permissions.date_end', '>=', $date->format('Y-m-d'))
                    ->orWhereNull('att_permissions.date_end');
            });
        }]);
    }

    

    public function scopeActivesWithPrincipalFunctionalPositions(Builder $query,$date): void
    {
        $query
        ->with(['functionalPositions' => function ($query) use ($date)
        {
            $query
            ->where('adm_employee_adm_functional_position.active',1)
            ->where('adm_employee_adm_functional_position.principal',1)
            ->where('adm_employee_adm_functional_position.date_start','<=',$date)
            ->where(function ($query) use ($date)
            {
                $query
                ->where('adm_employee_adm_functional_position.date_end','>=',$date)
                ->orWhereNull('adm_employee_adm_functional_position.date_end');
            })
            ->with(['organizationalUnit'])
            ->orderBy('adm_employee_adm_functional_position.principal','desc')
            ->orderBy('adm_employee_adm_functional_position.id','desc');
        }])
        ->where('active',1);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'adm_document_type_adm_employee', 'adm_employee_id', 'adm_document_type_id')
            ->withPivot(['value']);
    }

    public function paymentVoucherFiles()
    {
        return $this->hasMany(PaymentVoucherFile::class);
    }

    public function employeeRequests()
    {
        return $this->hasMany(EmployeeRequest::class, 'employee_id_affected');
    }

    public function employeeRequestTypeElements(): HasManyThrough
    {
        return $this->hasManyThrough(EmployeeRequestTypeElement::class, EmployeeRequest::class);
    }

    public function resourceTypes()
    {
        return $this->hasMany(ResourceType::class, 'adm_employee_id');
    }

    public function entries(): HasMany
    {
        return  $this->HasMany(Entry::class, 'adm_employee_id', 'id');
    }

    public function directories(): HasMany
    {
        return  $this->HasMany(Directory::class, 'adm_employee_id', 'id');
    }

    public function greetingSents()
    {
        return $this->hasMany(EmployeeBirthdayGreetingSent::class, 'adm_employee_id');
    }

    public function vehicles()
    {
        return $this->hasMany(EmployeeVehicle::class, 'adm_employee_id');
    }

    public function scopeBirthdaysBetweenDates(Builder $query, $dateIni, $dateEnd ): void
    {
        $dateIni = Carbon::parse($dateIni);
        $dateEnd = Carbon::parse($dateEnd);
        $date = Carbon::now();
        
        $query->with(['functionalPositions' => function ($query) use ($date)
        {
            $query
            ->where('adm_employee_adm_functional_position.active',1)
            ->where('adm_employee_adm_functional_position.principal',1)
            ->where('adm_employee_adm_functional_position.date_start','<=',$date)
            ->where(function ($query) use ($date)
            {
                $query
                ->where('adm_employee_adm_functional_position.date_end','>=',$date)
                ->orWhereNull('adm_employee_adm_functional_position.date_end');
            })
            ->with(['organizationalUnit'])
            ->orderBy('adm_employee_adm_functional_position.principal','desc')
            ->orderBy('adm_employee_adm_functional_position.id','desc');
        }])->where(function ($query) use ($dateIni) {
            $query->whereMonth('birthday', '>=', date('m', strtotime($dateIni)))
                ->whereDay('birthday', '>=', date('d', strtotime($dateIni)));
        })
        ->where(function ($query) use ($dateEnd) {
            $query->whereMonth('birthday', '<=', date('m', strtotime($dateEnd)))
                ->whereDay('birthday', '<=', date('d', strtotime($dateEnd)));
        })
        ->where('active',1);
    }

    public function scopeBirthdaysInMonth(Builder $query, $dateIni ): void
    {
        $dateIni = Carbon::parse($dateIni);
        $date = Carbon::now();
        
        $query->with(['functionalPositions' => function ($query) use ($date)
        {
            $query
            ->where('adm_employee_adm_functional_position.active',1)
            ->where('adm_employee_adm_functional_position.principal',1)
            ->where('adm_employee_adm_functional_position.date_start','<=',$date)
            ->where(function ($query) use ($date)
            {
                $query
                ->where('adm_employee_adm_functional_position.date_end','>=',$date)
                ->orWhereNull('adm_employee_adm_functional_position.date_end');
            })
            ->with(['organizationalUnit'])
            ->orderBy('adm_employee_adm_functional_position.principal','desc')
            ->orderBy('adm_employee_adm_functional_position.id','desc');
        }])->where(function ($query) use ($dateIni) {
            $query->whereMonth('birthday', '=', date('m', strtotime($dateIni)));
        })
        ->where('active',1);
    }

    public function compensatories(): HasMany
    {
        return $this->hasMany(Compensatory::class, 'adm_employee_id', 'id');
    }

    public function compensatoriesManaged(): HasMany
    {
        return $this->hasMany(Compensatory::class, 'adm_employee_boss_id', 'id');
    }

    

    public function requestTypes()
    {
        return $this->hasMany(RequestType::class, 'adm_employee_id');
    }

    public function requestCategories()
    {
        return $this->belongsToMany(RequestCategory::class, 'adm_employee_req_request_category', 'adm_employee_id', 'req_request_category_id');
    }

    public function requestDetails()
    {
        return $this->hasMany(RequestDetail::class, 'adm_employee_id');
    }

    public function requestResponses()
    {
        return $this->hasMany(RequestResponse::class, 'adm_employee_id');
    }

    public function courtesyTime()
    {
        return $this->hasMany(EmployeeCourtesyTime::class, 'adm_employee_id');
    }

    public function employeeCourtesyTime(): HasMany
    {
        return  $this->HasMany(EmployeeCourtesyTime::class, 'adm_employee_id', 'id');
    }
    
    public function accessCards()
    {
        return $this->hasMany(AccessCard::class, 'adm_employee_id');
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'adm_employee_id','id');
    }

    public function permissionRequestsGenerated(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'adm_employee_generated_id','id');
    }

    public function permissionRequestsBossManage(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'adm_employee_boss_id','id');
    }

    public function permissionRequestsHRManage(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'adm_employee_hr_id','id');
    }

}