<?php

namespace App\Providers;

use App\Agents\AgentRegistry;
use App\Agents\DocumentQAAgent;
use App\Agents\MathSolverAgent;
use App\Agents\QuizGeneratorAgent;
use App\Services\IntentClassifier;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentRegistry::class);
        $this->app->singleton(IntentClassifier::class);
    }

    public function boot(AgentRegistry $registry): void
    {
        $registry->register($this->app->make(DocumentQAAgent::class));
        $registry->register($this->app->make(MathSolverAgent::class));
        $registry->register($this->app->make(QuizGeneratorAgent::class));
    }
}
