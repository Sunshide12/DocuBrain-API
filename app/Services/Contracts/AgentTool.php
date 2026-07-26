<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;

interface AgentTool
{
    public function key(): string;

    public function name(): string;

    /**
     * Human-readable description of what this tool does. Used as the default
     * seed value for the `tools` table, and fed to the OrchestratorRouter so
     * the LLM can decide when to route a message to this tool.
     */
    public function description(): string;

    /** Whether this tool requires a document attached to the conversation to run. */
    public function requiresDocument(): bool;

    public function execute(ToolContext $context): ToolResponse;
}
