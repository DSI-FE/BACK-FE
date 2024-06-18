<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeBirthdayGreetingSent extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'adm_employee_greeting_sent';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $increment = true;

    public $fillable = [
        'adm_employee_id',
        'email',
        'birthday',
        'status',
    ];

    public $hidden = [
        'created_at',
        'updated_at',
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
            ->useLogName('BirthdayGreeting')
            ->logAll()
            ->logOnlyDirty();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'adm_employee_id', 'id');
    }
}
