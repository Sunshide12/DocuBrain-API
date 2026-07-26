<?php

declare(strict_types=1);

namespace App\Agents\Tools;

use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;
use App\Models\DocumentMathPage;
use App\Services\Contracts\AgentTool;
use App\Services\Contracts\OpenRouterClient;

class MathSolverTool implements AgentTool
{
    public function __construct(
        private readonly OpenRouterClient $openRouter,
    ) {}

    public function key(): string
    {
        return 'math_solver';
    }

    public function name(): string
    {
        return 'Math Solver';
    }

    public function description(): string
    {
        return 'Resolve and explain mathematical problems, equations, formulas, integrals, derivatives, algebra, and calculations step by step.';
    }

    public function requiresDocument(): bool
    {
        return false;
    }

    public function execute(ToolContext $context): ToolResponse
    {
        if ($context->intent->isTopicMissing()) {
            return new ToolResponse(
                answer: 'Solo puedo ayudarte con problemas matemáticos. Si tienes una ecuación, fórmula o ejercicio que resolver, ¡estaré encantado de ayudarte!',
                agentKey: $this->key(),
            );
        }

        $mathContext = $this->gatherMathContext($context);
        $prompt = $this->buildPrompt($context->question, $mathContext);

        $answer = $this->openRouter->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['timeout' => 90]);

        return new ToolResponse(
            answer: $answer,
            agentKey: $this->key(),
            responseType: 'steps',
            metadata: [
                'agent_used' => $this->key(),
                'had_document_context' => $mathContext !== null,
            ],
        );
    }

    private function gatherMathContext(ToolContext $context): ?string
    {
        if ($context->document === null) {
            return null;
        }

        $mathPages = DocumentMathPage::where('document_id', $context->document->id)
            ->orderBy('page_number')
            ->get();

        if ($mathPages->isEmpty()) {
            return null;
        }

        return $mathPages->map(function ($page) {
            return "Página {$page->page_number}:\n{$page->content}";
        })->implode("\n\n---\n\n");
    }

    private function buildPrompt(string $question, ?string $mathContext): string
    {
        $contextBlock = '';
        if ($mathContext !== null) {
            $contextBlock = <<<EOT

<contexto_documento>
The following mathematical content was extracted from the user's PDF document. Use it as reference if relevant to the problem:

{$mathContext}
</contexto_documento>

EOT;
        }

        return <<<EOT
<rol>
You are a patient and thorough math tutor. Your goal is to help the student understand how to solve the problem, not just give the answer. Explain each step clearly, as if the student is seeing this type of problem for the first time.
</rol>

<instrucciones>
- Solve the mathematical problem step by step.
- Number each step clearly.
- Explain WHY each step is done, not just what is done.
- Use LaTeX notation for all formulas and expressions (inline with $...$ and display with $$...$$).
- Highlight the final answer clearly at the end.
- If the problem comes from the document context, reference it.
- If no document context is provided, solve using your mathematical knowledge.
- Answer in the same language the user used to ask the question.
</instrucciones>
{$contextBlock}
<formato>
Respond in Markdown with LaTeX math notation. Structure:
1. **Problem identification**: Restate what needs to be solved.
2. **Step-by-step solution**: Numbered steps with explanations.
3. **Final answer**: Clearly marked with "**Resultado:**" or "**Answer:**".
</formato>

<restricciones>
- Do not invent numerical values that are not given in the problem.
- If a necessary value is missing, ask the user to provide it.
</restricciones>

<pregunta>
{$question}
</pregunta>
EOT;
    }
}
