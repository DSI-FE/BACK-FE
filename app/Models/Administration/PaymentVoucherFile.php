<?php

namespace App\Models\Administration;

use Spatie\Activitylog\LogOptions;
use App\Models\Administration\Employee;
use Illuminate\Database\Eloquent\Model;
use League\CommonMark\Node\Block\Document;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Administration\PaymentVoucher;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentVoucherFile extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'adm_payment_voucher_files';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $increment = true;

    public $fillable = [
        'document_id',
        'email',
        'file_name',
        'file_type',
        'file_size',
        'file_path',
        'sent',
        'adm_employee_id',
        'adm_payment_voucher_id'
    ];

    public $hidden = [
        'created_at',
        'deleted_at',
    ];

    protected $casts = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_voucher_file')
            ->logAll()
            ->logOnlyDirty();
    }

    public function document()
    {
        return $this->hasOne(Document::class, 'adm_document_type_adm_employee');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'adm_employee_id');
    }

    public function paymentVoucher()
    {
        return $this->belongsTo(PaymentVoucher::class, 'adm_payment_voucher_id');
    }
}
