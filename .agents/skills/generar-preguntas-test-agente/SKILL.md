---
name: generar-preguntas-test-agente
description: Generates 5 useful questions/inputs to test a RAG agent's behavior, chaining off the identificar-agente skill.
---

# Agent Evaluation Question Generator

When the user asks for ideas, questions, or inputs to test a specific agent's behavior, follow this flow strictly.

## 1. Required First Step: Identify the Agent
Before inventing any questions, you must know what the agent actually does — without guessing.

**Critical rule: run the `identificar-agente` skill's steps first.** Open the corresponding file in `app/Agents/` with your reading tools and extract its `name()`, `description()`, and `supportedIntents()`. Do not proceed to step 2 until you have the agent's real logic in context.

## 2. Design the 5 Test Cases
Based on the actual code you read, generate 5 highly targeted user-input questions the user can run against the agent (in Laravel Tinker or the chat UI).

Cover exactly these 5 evaluation angles:

1. **Happy Path**: a perfect, direct, clear question the agent was designed for (based on its description and intents). *Goal: verify standard, ideal behavior.*
2. **Edge Case**: a very complex, long, or ambiguous question, still related to its task. *Goal: evaluate how the LLM reasons when the user isn't clear.*
3. **Controlled Error Test (Out of Topic)**: a question that forces the `isTopicMissing()` branch. *Goal: verify the "no information found" fallback fires correctly.*
4. **Format-Breaking Test**: a request that tries to trick the agent into breaking its output format (e.g. if it must return JSON, ask it to tell a joke in plain text). *Goal: verify the System Prompt blocks injection attempts.*
5. **Intent-Boundary Test (Jailbreak / Intent Crossing)**: a misleading question that looks aimed at ANOTHER agent but mentions this agent's topics. *Goal: verify it doesn't assume a role that isn't its own.*

## 3. Present to the User
Deliver the 5 questions in Markdown (tables or clear bullets work well). For each one, add a line explaining:
- **What are we evaluating?**
- **What's the correct/expected response?** (e.g. "Should return JSON with 2 keys", or "Should politely say it doesn't know the answer").

---
Checkpoint: did you run identificar-agente first, and do all 5 angles appear?
