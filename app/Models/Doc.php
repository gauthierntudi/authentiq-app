<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doc extends Model
{
    protected $table = 'DOCS';

    protected $primaryKey = 'id_doc';

    public $timestamps = false;

    protected $fillable = [
        'nom_doc',
        'type_doc',
        'montant',
        'duree',
        'validite',
        'ownership',
    ];

    protected $casts = [
        'montant' => 'float',
        'duree' => 'integer',
    ];
}
