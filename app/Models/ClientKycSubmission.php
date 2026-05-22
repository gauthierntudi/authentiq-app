<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientKycSubmission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'CLIENT_KYC_SUBMISSIONS';

    protected $primaryKey = 'id_submission';

    public $timestamps = false;

    protected $fillable = [
        'id_client',
        'status',
        'type_piece_identite',
        'recto_path',
        'verso_path',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }
}
