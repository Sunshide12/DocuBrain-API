<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\Contracts\AgentTool;
use Illuminate\Contracts\Container\Container;

/**
 * Maps tool keys to their class. Resolution goes through the container lazily on
 * every `resolve()` call — tools are NOT instantiated at boot time, so container
 * bindings swapped later (e.g. mocked dependencies in tests) are always honored.
 */
class ToolRegistry
{
    /** @var array<string, class-string<AgentTool>> */
    private array $tools = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /** @param  class-string<AgentTool>  $toolClass */
    public function register(string $key, string $toolClass): void
    {
        $this->tools[$key] = $toolClass;
    }

    public function resolve(string $key): AgentTool
    {
        if (! isset($this->tools[$key])) {
            throw new \InvalidArgumentException("Unknown tool: {$key}");
        }

        return $this->container->make($this->tools[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->tools[$key]);
    }

    /** @return array<string, class-string<AgentTool>> */
    public function all(): array
    {
        return $this->tools;
    }
}
