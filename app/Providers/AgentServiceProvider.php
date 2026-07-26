<?php

namespace App\Providers;

use App\Agents\OrchestratorAgent;
use App\Agents\ToolRegistry;
use App\Agents\Tools\ClarificationTool;
use App\Agents\Tools\DocumentQATool;
use App\Agents\Tools\GreetingsTool;
use App\Agents\Tools\MathSolverTool;
use App\Agents\Tools\QuizGeneratorTool;
use App\Services\IntentClassifier;
use App\Services\OrchestratorRouter;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(IntentClassifier::class);
        $this->app->singleton(OrchestratorRouter::class);
        $this->app->singleton(OrchestratorAgent::class);
    }

    public function boot(ToolRegistry $registry): void
    {
        $registry->register('document_qa', DocumentQATool::class);
        $registry->register('quiz_generator', QuizGeneratorTool::class);
        $registry->register('math_solver', MathSolverTool::class);
        $registry->register('greetings', GreetingsTool::class);
        $registry->register('clarification', ClarificationTool::class);
    }
}
