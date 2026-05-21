<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Model
{
    protected $table = 'USERS';

    protected $primaryKey = 'id_user';

    public $timestamps = false;

    protected $hidden = ['password'];

    protected $fillable = [
        'nom_complet',
        'tel',
        'email',
        'password',
        'photo',
        'role',
        'id_province',
        'id_ville',
        'id_commune',
        'affectation',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'id_province');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'id_ville');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'id_commune');
    }
}
