<?php

namespace App\Models\Administration;

use App\Models\Administration\PaymentVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentVoucherError extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_payment_voucher_errors';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $fillable = [
        'user_id',
        'adm_payment_voucher_id',
        'error',
        'file',
    ];

    public $hidden = [
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_voucher_error')
            ->logAll()
            ->logOnlyDirty();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment_voucher()
    {
        return $this->belongsTo(PaymentVoucher::class);
    }
}
