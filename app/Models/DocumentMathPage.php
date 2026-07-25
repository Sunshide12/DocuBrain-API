<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMathPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'page_number',
        'content',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
