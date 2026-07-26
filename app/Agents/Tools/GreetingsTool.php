<?php

declare(strict_types=1);

namespace App\Agents\Tools;

use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;
use App\Models\Tool;
use App\Services\Contracts\AgentTool;

/**
 * Handles greetings and "what can you do?" style questions. The OrchestratorRouter
 * routes chit-chat here instead of the orchestrator answering directly, keeping the
 * orchestrator free of any business logic.
 */
class GreetingsTool implements AgentTool
{
    public function key(): string
    {
        return 'greetings';
    }

    public function name(): string
    {
        return 'Greetings';
    }

    public function description(): string
    {
        return 'Responds to greetings, small talk, and questions about what the assistant can do.';
    }

    public function requiresDocument(): bool
    {
        return false;
    }

    public function execute(ToolContext $context): ToolResponse
    {
        $capabilities = Tool::query()
            ->where('is_enabled', true)
            ->whereNotIn('key', [$this->key(), 'clarification'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Tool $tool) => "- **{$tool->name}**: {$tool->description}")
            ->implode("\n");

        $answer = "¡Hola! 👋 Soy tu asistente para trabajar con documentos. Esto es lo que puedo hacer:\n\n{$capabilities}\n\n¿En qué te ayudo?";

        return new ToolResponse(
            answer: $answer,
            agentKey: $this->key(),
        );
    }
}
