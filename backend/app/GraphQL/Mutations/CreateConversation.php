<?php

namespace App\GraphQL\Mutations;

use App\Models\Conversation;
use App\Models\Document;

class CreateConversation
{
    public function __invoke($_, array $args): Conversation
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $documentId = $args['document_id'] ?? null;
        $title = $args['title'] ?? null;

        if (!$title) {
            if ($documentId) {
                $document = Document::where('user_id', $userId)->findOrFail($documentId);
                $title = "Chat sobre: " . $document->original_name;
            } else {
                $title = "Búsqueda Global";
            }
        }

        return Conversation::create([
            'user_id' => $userId,
            'document_id' => $documentId,
            'title' => $title,
            'total_tokens' => 0,
        ]);
    }
}
