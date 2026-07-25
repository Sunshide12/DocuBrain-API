<?php

namespace App\GraphQL\Mutations;

use App\Agents\AgentRegistry;
use App\DTOs\AgentContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\IntentClassifier;
use Illuminate\Support\Facades\Auth;

class SendMessage
{
    public function __construct(
        private readonly AgentRegistry    $registry,
        private readonly IntentClassifier $classifier,
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

        // Classify the user's intent before handing off to the agent.
        // The ClassifiedIntent travels inside AgentContext so every agent can
        // inspect it without performing its own LLM classification call.
        $classifiedIntent = $this->classifier->classify(
            message:          $args['content'],
            supportedIntents: $agent->supportedIntents(),
            document:         $conversation->document,
            userId:           $user->id,
        );

        $context = new AgentContext(
            question:     $args['content'],
            conversation: $conversation,
            document:     $conversation->document,
            userId:       $user->id,
            intent:       $classifiedIntent,
        );

        $result = $agent->handle($context);

        return $conversation->messages()->create([
            'role'             => 'assistant',
            'content'          => $result->answer,
            'source_chunk_ids' => $result->sourceChunks,
            'response_type'    => $result->responseType,
            'metadata'         => $result->metadata ? json_encode($result->metadata) : null,
        ]);
    }
}
