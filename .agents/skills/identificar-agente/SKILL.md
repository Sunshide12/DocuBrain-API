---
name: identificar-agente
description: Quickly identifies and reads an agent's properties (name, description, intents) from app/Agents/.
---

# Agent Identifier (Direct Reader)

**Critical rule: do NOT run `grep_search` or broad global searches.** All agents live strictly in `app/Agents/`.

This skill is a sub-step used by other skills (e.g. `generar-preguntas-test-agente`) before they can reason about a specific agent.

When the user mentions an agent and you need to know what it does (to test it, audit it, or generate questions for it), follow this exact flow to save tokens and avoid hallucinating:

1. The class and file always follow the format `[Name]Agent.php` (e.g. `DocumentQAAgent.php`, `QuizGeneratorAgent.php`).
2. **Step 1**: if you're not sure of the exact name, run `list_dir` on `app/Agents/` to see the available files at minimal token cost.
3. **Step 2**: use `view_file` to read the source of the agent file in question.
4. **Step 3**: from the code you read, retain in context:
   - The return value of `name()`
   - The return value of `description()`
   - The `supportedIntents()` array
5. Done — you now have full context on the agent's identity. Return to the original task or the calling skill (e.g. `generar-preguntas-test-agente`) with this information.

---
Checkpoint: do you have name(), description(), and supportedIntents() in context before proceeding?
