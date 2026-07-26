<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'source_chunk_ids',
        'response_type',
        'metadata',
        'agent_key',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected $casts = [
        'source_chunk_ids' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
