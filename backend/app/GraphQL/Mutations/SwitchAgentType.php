<?php

namespace App\GraphQL\Mutations;

use App\Agents\AgentRegistry;
use App\Models\Conversation;
use App\Models\Quiz;
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

        // When switching to quiz_generator, auto-generate a quiz if none exists yet
        if ($agentType === 'quiz_generator' && $conversation->document_id) {
            $alreadyHasQuiz = Quiz::where('document_id', $conversation->document_id)
                ->whereIn('status', ['ready', 'generating'])
                ->exists();

            if (!$alreadyHasQuiz) {
                $document = $conversation->document;
                if ($document) {
                    \App\Jobs\GenerateAutoQuizJob::dispatch($document);
                }
            }
        }

        return $conversation;
    }
}
