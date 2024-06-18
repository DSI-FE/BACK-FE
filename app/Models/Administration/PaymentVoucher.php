<?php

namespace App\Models\Administration;

use Spatie\Activitylog\LogOptions;
use App\Models\Administration\Employee;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentVoucher extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_payment_vouchers';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'year',
        'month',
        'description',
        'finished',
    ];

    protected $casts = [];

    public $hidden = [
        'created_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_voucher')
            ->logAll()
            ->logOnlyDirty();
    }

    public function paymentVoucherFiles()
    {
        return $this->hasMany(PaymentVoucherFile::class, 'adm_payment_voucher_id', 'id');
    }

    public function paymentVoucherErrors()
    {
        return $this->hasMany(PaymentVoucherError::class, 'adm_payment_voucher_id', 'id');
    }
}
