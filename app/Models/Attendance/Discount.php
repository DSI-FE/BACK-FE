<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'att_discounts';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable =
    [

        'date',
        'discount',
        'time_discounted',
        'time_not_worked',
        'time_justified_pay',
        'time_justified_no_pay',
        'adm_employee_id'
    ];

    public $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];
    
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'adm_employee_id', 'id');
    }

    public function scopeByDateEmployee(Builder $query,$date,$employeeId): void
    {
        $query->whereDate('date',$date)->where('adm_employee_id',$employeeId);
    }
}
