<?php

namespace App\GraphQL\Mutations;

use App\Agents\OrchestratorAgent;
use App\Exceptions\FriendlyException;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class SendMessage
{
    public function __construct(
        private readonly OrchestratorAgent $orchestrator,
    ) {}

    public function __invoke($_, array $args): Message
    {
        $conversation = Conversation::findOrFail($args['conversation_id']);
        $user = Auth::user();

        if ($conversation->document === null) {
            throw new FriendlyException('Seleccioná un documento para chatear.');
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $args['content'],
        ]);

        $result = $this->orchestrator->handle($args['content'], $conversation, $user->id);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result->answer,
            'source_chunk_ids' => $result->sourceChunks,
            'response_type' => $result->responseType,
            'metadata' => $result->metadata ? json_encode($result->metadata) : null,
            'agent_key' => $result->agentKey,
        ]);
    }
}
