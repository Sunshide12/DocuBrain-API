<?php

namespace App\Services\Contracts;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;

interface AgentHandler
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    public function handle(AgentContext $context): AgentResponse;
}
