<?php

namespace App\GraphQL\Mutations;

use App\Agents\AgentRegistry;
use App\Models\Conversation;
use App\Models\Document;

class CreateConversation
{
    public function __construct(
        private readonly AgentRegistry $registry,
    ) {}

    public function __invoke($_, array $args): Conversation
    {
        $userId     = \Illuminate\Support\Facades\Auth::id();
        $documentId = $args['document_id'] ?? null;
        $title      = $args['title'] ?? null;
        $agentType  = $args['agent_type'] ?? 'document_qa';

        if (!$this->registry->has($agentType)) {
            throw new \InvalidArgumentException("Unknown agent type: {$agentType}");
        }

        if (!$title) {
            if ($documentId) {
                $document = Document::where('user_id', $userId)->findOrFail($documentId);
                $title    = "Chat sobre: " . $document->original_name;
            } else {
                $title = "Búsqueda Global";
            }
        }

        $attributes = [
            'user_id'    => $userId,
            'document_id' => $documentId,
            'agent_type' => $agentType,
        ];

        try {
            $conversation = Conversation::withoutGlobalScope('owned')->firstOrCreate(
                $attributes,
                [
                    'title'        => $title,
                    'total_tokens' => 0,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $conversation = Conversation::withoutGlobalScope('owned')->where($attributes)->first();

            if (!$conversation) {
                throw $e;
            }
        }

        return $conversation;
    }
}
