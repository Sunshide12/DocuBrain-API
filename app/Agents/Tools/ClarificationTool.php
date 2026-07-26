<?php

declare(strict_types=1);

namespace App\Agents\Tools;

use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;
use App\Services\Contracts\AgentTool;

/**
 * Returned by the router when no other tool clearly matches the user's message.
 * Asks the user to clarify instead of guessing — the next turn's history gives
 * the router enough context to route correctly.
 */
class ClarificationTool implements AgentTool
{
    public function key(): string
    {
        return 'clarification';
    }

    public function name(): string
    {
        return 'Clarification';
    }

    public function description(): string
    {
        return 'Asks the user to clarify their request when it is too ambiguous to route to a specific tool.';
    }

    public function requiresDocument(): bool
    {
        return false;
    }

    public function execute(ToolContext $context): ToolResponse
    {
        return new ToolResponse(
            answer: 'No estoy seguro de cómo ayudarte con eso. ¿Podrías darme más detalles? Por ejemplo: pedime que responda una pregunta sobre tu documento, que genere un quiz, o que resuelva un problema matemático.',
            agentKey: $this->key(),
        );
    }
}
