<?php

namespace App\GraphQL\Mutations;

use App\Models\Conversation;
use App\Models\Document;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class CreateConversation
{
    public function __invoke($_, array $args): Conversation
    {
        $userId = Auth::id();
        $documentId = $args['document_id'] ?? null;
        $title = $args['title'] ?? null;

        if (! $title) {
            if ($documentId) {
                $document = Document::where('user_id', $userId)->findOrFail($documentId);
                $title = 'Chat sobre: '.$document->original_name;
            } else {
                $title = 'Búsqueda Global';
            }
        }

        $attributes = [
            'user_id' => $userId,
            'document_id' => $documentId,
            'agent_type' => 'orchestrator',
        ];

        try {
            $conversation = Conversation::withoutGlobalScope('owned')->firstOrCreate(
                $attributes,
                [
                    'title' => $title,
                    'total_tokens' => 0,
                ]
            );
        } catch (QueryException $e) {
            $conversation = Conversation::withoutGlobalScope('owned')->where($attributes)->first();

            if (! $conversation) {
                throw $e;
            }
        }

        return $conversation;
    }
}
