<?php

namespace App\GraphQL\Mutations;

use App\Agents\AgentRegistry;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;

class SwitchAgentType
{
    public function __construct(
        private readonly AgentRegistry $registry,
    ) {}

    public function __invoke($_, array $args): Conversation
    {
        $conversation = Conversation::findOrFail($args['conversation_id']);

        // Ensure the conversation belongs to the authenticated user
        if ($conversation->user_id !== Auth::id()) {
            throw new \Exception('Unauthorized');
        }

        $agentType = $args['agent_type'];

        if (!$this->registry->has($agentType)) {
            throw new \InvalidArgumentException("Unknown agent type: {$agentType}");
        }

        $conversation->agent_type = $agentType;
        $conversation->save();

        return $conversation;
    }
}
