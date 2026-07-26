---
name: auditar-prompt-agente
description: Skill for diagnosing, auditing, and fixing the system prompt of an existing agent that is failing or hallucinating.
---

# Prompt Auditor (Prompt Troubleshooter)

**Critical rule: when this skill is invoked to fix an existing agent's behavior, STOP.** The goal is not to change PHP code, but to fix the agent's "brain" (the prompt). Act as an expert LLM QA Auditor and ask the user these 5 key questions to diagnose the problem before proposing a fix.

## The 5 Key Questions
Present these questions to the user to pinpoint the failure (keep them in Spanish since the user communicates in Spanish):

1. **El Síntoma**: ¿Cuál es la "alucinación" o el comportamiento incorrecto exacto que está mostrando el agente actualmente?
2. **El Ideal**: ¿Qué respuesta o comportamiento esperabas obtener en su lugar?
3. **El Contexto**: ¿Puedes darme un ejemplo del input (la pregunta del usuario o el fragmento del PDF) que causó este fallo?
4. **Fallo de Formato o de Lógica**: ¿El agente falló en seguir el formato (ej. devolvió texto en vez de JSON) o falló en el razonamiento lógico (inventó información)?
5. **Ajuste de Temperatura/Creatividad**: Para solucionarlo, ¿prefieres que hagamos el prompt más "estricto y conservador" (que diga "No sé" más seguido) o que siga siendo "creativo pero guiado"?

## After the Interview
Once the user answers, do the following:
1. Ask the user for the agent's current System Prompt (if not already in context).
2. Analyze which part of the prompt caused the ambiguity.
3. Write the **refactored System Prompt**.
4. Explain to the user exactly which words or instructions you added (e.g. "I added the rule *If you don't know the answer, output exactly NULL* to stop it from inventing data").

---
Checkpoint: did you explain the specific fix to the user, not just hand over a new prompt?
