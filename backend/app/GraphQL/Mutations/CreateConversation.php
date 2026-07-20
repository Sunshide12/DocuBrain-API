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

        // Generate default title if not provided
        if (!$title) {
            if ($documentId) {
                $document = Document::where('user_id', $userId)->findOrFail($documentId);
                $title = "Chat sobre: " . $document->original_name;
            } else {
                $title = "Búsqueda Global";
            }
        }

        // Use firstOrCreate to return existing conversation if one exists
        // This makes conversations persistent per document
        // Note: We use withoutGlobalScope('owned') because the global scope
        // interferes with firstOrCreate's SELECT query, causing it to not find
        // existing conversations and then fail on INSERT due to the unique constraint
        $attributes = [
            'user_id' => $userId,
            'document_id' => $documentId,
        ];

        try {
            $conversation = Conversation::withoutGlobalScope('owned')->firstOrCreate(
                $attributes,
                [
                    'title' => $title,
                    'total_tokens' => 0,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // A concurrent request won the race and inserted the row first,
            // tripping the unique constraint. Fetch the row it created instead of failing.
            $conversation = Conversation::withoutGlobalScope('owned')->where($attributes)->first();

            if (!$conversation) {
                throw $e;
            }
        }

        return $conversation;
    }
}
