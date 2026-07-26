---
name: identificar-agente
description: Quickly identifies and reads a tool's properties (name, description, requiresDocument) from app/Agents/Tools/, or the router itself from app/Agents/OrchestratorAgent.php.
---

# Agent Identifier (Direct Reader)

**Critical rule: do NOT run `grep_search` or broad global searches.** There is a single top-level agent, `App\Agents\OrchestratorAgent` (`app/Agents/OrchestratorAgent.php`), which routes every turn to exactly one Tool. All tools live strictly in `app/Agents/Tools/`.

This skill is a sub-step used by other skills (e.g. `generar-preguntas-test-agente`) before they can reason about a specific tool.

When the user mentions an agent/tool and you need to know what it does (to test it, audit it, or generate questions for it), follow this exact flow to save tokens and avoid hallucinating:

1. The class and file always follow the format `[Name]Tool.php` (e.g. `DocumentQATool.php`, `QuizGeneratorTool.php`, `MathSolverTool.php`, `GreetingsTool.php`, `ClarificationTool.php`).
2. **Step 1**: if you're not sure of the exact name, run `list_dir` on `app/Agents/Tools/` to see the available files at minimal token cost.
3. **Step 2**: use `view_file` to read the source of the tool file in question.
4. **Step 3**: from the code you read, retain in context:
   - The return value of `name()`
   - The return value of `description()` — this is also what's fed to the router's LLM call, and seeded into the `tools` DB table, so it drives routing quality directly.
   - The return value of `requiresDocument()`
5. Done — you now have full context on the tool's identity. Return to the original task or the calling skill (e.g. `generar-preguntas-test-agente`) with this information.

If the user is asking about routing/dispatch logic itself rather than a specific capability, read `app/Agents/OrchestratorAgent.php` and `app/Services/OrchestratorRouter.php` instead — those hold the one-LLM-call tool+intent selection, not any individual tool.

---
Checkpoint: do you have name(), description(), and requiresDocument() in context before proceeding (or did you correctly redirect to OrchestratorAgent/OrchestratorRouter for routing questions)?
