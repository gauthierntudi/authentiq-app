<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ville extends Model
{
    protected $table = 'VILLES';

    protected $primaryKey = 'id_ville';

    public $timestamps = false;

    protected $fillable = ['nom', 'id_province'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'id_province');
    }
}
