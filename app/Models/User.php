<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Asistencia\ResResource;
use App\Models\Administration\Employee;
use App\Models\Administration\PaymentVoucherError;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\MyResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'lastname',
        'username',
        'email',
        'password',
        'change_password',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = true;

    protected static $recordEvents = [
        'created',
        'updated',
        'deleted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logAll()
            ->logOnlyDirty();
    }

    public function reservations()
    {
        return $this->belongsToMany(ResResource::class, 'res_resource_user', 'res_resource_id', 'user_id')->withPivot(['subject', 'description', 'datetime_start', 'datetime_end']);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function paymentVoucherErrors()
    {
        return $this->hasMany(PaymentVoucherError::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MyResetPasswordNotification($token));
    }
}
