<?php

namespace App\Agents;

use App\Services\Contracts\AgentHandler;

class AgentRegistry
{
    /** @var array<string, AgentHandler> */
    private array $agents = [];

    public function register(AgentHandler $agent): void
    {
        $this->agents[$agent->key()] = $agent;
    }

    public function resolve(string $key): AgentHandler
    {
        if (!isset($this->agents[$key])) {
            throw new \InvalidArgumentException("Unknown agent: {$key}");
        }
        return $this->agents[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->agents[$key]);
    }

    /** @return array<string, AgentHandler> */
    public function all(): array
    {
        return $this->agents;
    }
}
