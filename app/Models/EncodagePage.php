<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncodagePage extends Model
{
    protected $table = 'ENCODAGES_PAGES';

    protected $primaryKey = 'id_page';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_encodage',
        'page_number',
        'file_path',
        'file_size',
        'ocr_text',
        'textract_status',
        'quality_score',
    ];

    protected $casts = [
        'quality_score' => 'float',
    ];

    public function encodage(): BelongsTo
    {
        return $this->belongsTo(Encodage::class, 'id_encodage');
    }
}
