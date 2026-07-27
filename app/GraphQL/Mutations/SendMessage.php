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

        // Guard before persisting: an all-whitespace message would otherwise be
        // stored and sent to the orchestrator, which bills an LLM call to answer
        // nothing and leaves a blank turn in the thread.
        $content = trim($args['content']);

        if ($content === '') {
            throw new FriendlyException('Escribí una pregunta para poder ayudarte.');
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        $result = $this->orchestrator->handle($content, $conversation, $user->id);

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
