<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'question', 'type', 'options',
        'correct_answer', 'explanation', 'page_number', 'sort_order',
    ];

    protected $casts = ['options' => 'array'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
