<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNotification extends Model
{
    protected $table = 'CLIENT_NOTIFICATIONS';

    protected $primaryKey = 'id_notification';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'id_client',
        'type',
        'title',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }
}
