<?php

namespace App\Services\Contracts;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;

interface AgentHandler
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /**
     * Return the list of actionable intents this agent understands.
     * The IntentClassifier feeds this list to the LLM so it classifies correctly.
     *
     * Rules:
     * - Do NOT include 'chat' — it is the implicit fallback for all agents.
     * - Use English verb phrases: generate_quiz, ask_question, solve_problem, etc.
     *
     * @return string[]
     */
    public function supportedIntents(): array;

    public function handle(AgentContext $context): AgentResponse;
}
