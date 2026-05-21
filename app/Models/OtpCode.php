<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $table = 'OTP_CODES';

    protected $primaryKey = 'id_otp';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_type',
        'user_id',
        'client_id',
        'code_otp',
        'expire_at',
        'attempts',
        'created_at',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
    ];
}
