<?php

namespace App\GraphQL\Mutations;

use App\Models\Conversation;
use App\Models\Message;

class SendMessage
{
    public function __invoke($_, array $args): Message
    {
        // The conversation global scope ensures the user owns it.
        $conversation = Conversation::findOrFail($args['conversation_id']);

        // 1. Create User message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $args['content'],
        ]);

        // 2. Create Placeholder Assistant Message
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Este es un mensaje temporal. La IA real (RAG) será implementada en la Fase 8.',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
        ]);

        return $assistantMessage;
    }
}
