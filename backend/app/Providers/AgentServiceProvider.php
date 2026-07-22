<?php

namespace App\Providers;

use App\Agents\AgentRegistry;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentRegistry::class);
    }

    public function boot(AgentRegistry $registry): void
    {
        $registry->register($this->app->make(\App\Agents\DocumentQAAgent::class));
        // Future agents registered here:
        // $registry->register($this->app->make(\App\Agents\MathSolverAgent::class));
        // $registry->register($this->app->make(\App\Agents\QuizGeneratorAgent::class));
    }
}
