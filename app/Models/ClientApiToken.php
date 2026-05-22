<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientApiToken extends Model
{
    protected $table = 'CLIENT_API_TOKENS';

    protected $primaryKey = 'id_token';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'id_client',
        'token_hash',
        'name',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
