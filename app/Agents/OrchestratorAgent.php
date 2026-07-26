<?php

declare(strict_types=1);

namespace App\Agents;

use App\DTOs\ClassifiedIntent;
use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;
use App\Models\Conversation;
use App\Services\IntentClassifier;
use App\Services\OrchestratorRouter;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for every conversation turn. Carries no business logic of its
 * own: it asks the OrchestratorRouter which tool should handle the message, builds
 * the ClassifiedIntent for that decision, and delegates to the resolved AgentTool.
 * Exceptions raised by a tool are caught here so the user always gets a friendly
 * message instead of a stack trace.
 */
class OrchestratorAgent
{
    /** How many previous messages to feed the router and the resolved tool as context. */
    private const HISTORY_SIZE = 3;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly OrchestratorRouter $router,
        private readonly IntentClassifier $topicVerifier,
    ) {}

    public function handle(string $question, Conversation $conversation, int $userId): ToolResponse
    {
        $history = $this->buildHistory($conversation);

        $routed = $this->router->route($question, $history);

        $document = $conversation->document;

        $topicInDocument = $this->topicVerifier->topicExistsInDocument(
            topic: $routed['topic'],
            isStructural: $routed['topic_type'] === 'structural',
            document: $document,
            userId: $userId,
        );

        $intent = new ClassifiedIntent(
            intent: $routed['intent'],
            topic: $routed['topic'],
            topicInDocument: $topicInDocument,
            confidence: 1.0,
            topicType: $routed['topic_type'],
        );

        $context = new ToolContext(
            question: $question,
            conversation: $conversation,
            document: $document,
            userId: $userId,
            intent: $intent,
            history: $history,
        );

        try {
            $tool = $this->registry->resolve($routed['tool']);

            return $tool->execute($context);
        } catch (\Throwable $e) {
            Log::error('Tool execution failed', [
                'tool' => $routed['tool'],
                'conversation_id' => $conversation->id,
                'exception' => $e->getMessage(),
            ]);

            return new ToolResponse(
                answer: 'Ocurrió un problema al procesar tu mensaje. Por favor, intentá de nuevo.',
                agentKey: $routed['tool'],
            );
        }
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->skip(1)
            ->take(self::HISTORY_SIZE)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])
            ->all();
    }
}
