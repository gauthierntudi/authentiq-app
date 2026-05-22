<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerifyGrant extends Model
{
    protected $table = 'DOCUMENT_VERIFY_GRANTS';

    protected $primaryKey = 'id_grant';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'id_encodage',
        'id_client_owner',
        'id_client_grantee',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function encodage(): BelongsTo
    {
        return $this->belongsTo(Encodage::class, 'id_encodage');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client_owner');
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client_grantee');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->isFuture();
    }
}
