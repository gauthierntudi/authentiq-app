<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encodage extends Model
{
    protected $table = 'ENCODAGES';

    protected $primaryKey = 'id_encodage';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id_doc',
        'id_client',
        'id_user',
        'type_doc',
        'montant',
        'id_province',
        'id_ville',
        'affectation',
        'files',
        'ocrTextFiles',
        'date_emission',
        'date_expiration',
        'id_commune',
        'status',
        'numero',
        'qr_path',
        'page_count',
    ];

    protected $casts = [
        'montant' => 'float',
        'page_count' => 'integer',
        'date_emission' => 'date',
        'date_expiration' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class, 'id_doc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'id_commune');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(EncodagePage::class, 'id_encodage');
    }

    public function associatedClients(): BelongsToMany
    {
        return $this->belongsToMany(
            Client::class,
            'ENCODAGE_CLIENTS',
            'id_encodage',
            'id_client',
            'id_encodage',
            'id_client',
        )->withPivot('created_at');
    }
}
