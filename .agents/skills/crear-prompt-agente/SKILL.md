---
name: crear-prompt-agente
description: Skill for designing a new RAG agent's system prompt from scratch by asking the user 5 key questions.
---

# Prompt Designer (System Prompt Creator)

**Critical rule: when this skill is invoked, STOP. Do not write code or generate the prompt immediately.** First, act as an expert Prompt Engineer and interview the user with these 5 key questions to gather the necessary context.

## The 5 Key Questions
Present these questions to the user (light rewording for natural phrasing is fine, but keep them in Spanish since the user communicates in Spanish):

1. **Rol y Tono**: ¿Cuál es la "persona" exacta o el rol que debe asumir el agente? *(Ej: Profesor universitario estricto, Asistente legal objetivo, Tutor amigable).*
2. **Input (Contexto)**: ¿Qué información de entrada recibirá exactamente en su contexto? *(Ej: Todo el historial del chat, solo la última pregunta, fragmentos de un PDF).*
3. **Formato de Salida**: ¿Cuál debe ser el formato estricto de su respuesta? *(Ej: Solo JSON con una estructura específica, Markdown con viñetas, Texto simple sin saludos).*
4. **Manejo de Incertidumbre (Fallback)**: ¿Qué debe responder o hacer si los datos extraídos del documento (RAG) no contienen la respuesta a la pregunta del usuario?
5. **Restricciones Críticas**: ¿Existen reglas inquebrantables, palabras prohibidas o límites que el agente jamás debe cruzar? *(Ej: Nunca inventar fechas, no dar consejos médicos).*

## After the Interview
Once the user answers all 5 questions, write the final **System Prompt** using prompt-engineering best practices:
- Use XML delimiters (e.g. `<contexto>`, `<instrucciones>`, `<formato>`).
- Be imperative and clear ("You are a...", "You must...").
- Include few-shot examples if the output format is complex.

---
Checkpoint: are all 5 answers reflected in the prompt, and are XML delimiters present?
