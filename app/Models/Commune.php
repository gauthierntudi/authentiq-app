<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commune extends Model
{
    protected $table = 'COMMUNES';

    protected $primaryKey = 'id_commune';

    public $timestamps = false;

    protected $fillable = ['nom', 'id_ville'];

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'id_ville');
    }
}
