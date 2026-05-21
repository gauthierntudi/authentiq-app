<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $table = 'CLIENTS';

    protected $primaryKey = 'id_client';

    public $timestamps = false;

    protected $fillable = [
        'is_active',
        'nom_complet',
        'tel',
        'email',
        'photo',
        'id_province',
        'id_ville',
        'adresse',
        'type_piece_identite',
        'numero_national',
        'numero_passeport',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'id_province');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'id_ville');
    }
}
