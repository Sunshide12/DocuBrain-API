<?php

namespace App\GraphQL\Mutations;

use App\Agents\AgentRegistry;
use App\DTOs\AgentContext;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class SendMessage
{
    public function __construct(
        private readonly AgentRegistry $registry,
    ) {}

    public function __invoke($_, array $args): Message
    {
        $conversation = Conversation::findOrFail($args['conversation_id']);
        $user         = Auth::user();

        $conversation->messages()->create([
            'role'    => 'user',
            'content' => $args['content'],
        ]);

        $agent = $this->registry->resolve($conversation->agent_type);

        $context = new AgentContext(
            question:     $args['content'],
            conversation: $conversation,
            document:     $conversation->document,
            userId:       $user->id,
        );

        $result = $agent->handle($context);

        return $conversation->messages()->create([
            'role'            => 'assistant',
            'content'         => $result->answer,
            'source_chunk_ids' => $result->sourceChunks,
            'response_type'   => $result->responseType,
            'metadata'        => $result->metadata ?: null,
        ]);
    }
}
