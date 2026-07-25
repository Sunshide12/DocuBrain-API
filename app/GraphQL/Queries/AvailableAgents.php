<?php

namespace App\GraphQL\Queries;

use App\Agents\AgentRegistry;

class AvailableAgents
{
    public function __construct(
        private readonly AgentRegistry $registry,
    ) {}

    public function __invoke($_, array $args): array
    {
        return array_values(array_map(fn($agent) => [
            'key'         => $agent->key(),
            'name'        => $agent->name(),
            'description' => $agent->description(),
        ], $this->registry->all()));
    }
}
