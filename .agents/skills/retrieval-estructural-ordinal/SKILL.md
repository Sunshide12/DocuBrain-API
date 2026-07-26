---
name: retrieval-estructural-ordinal
description: Rule for handling ordinal/positional references ("el primer ejercicio", "problema 2.1.1") in RAG retrieval — never resolve them with pure vector similarity.
---

# Retrieval de Referencias Estructurales/Ordinales

**Critical rule: pure cosine-similarity search must never be trusted to resolve ordinal or positional language** ("el primer ejercicio", "el último punto", "problema 2.1.1", "ejercicio 3"). Embeddings encode semantic content, not document position — the chunk closest in vector space to "responde el primer ejercicio" can be any numbered item in the document, not necessarily the one that appears first.

## Por qué existe esta regla
Bug real detectado en el entonces `DocumentQAAgent` (hoy `DocumentQATool`): con threshold permisivo (0.1), preguntar "responde el primer ejercicio" sobre un PDF de ejercicios devolvía el Problema 2.2.5 en vez del Problema 2.1.1 (el que realmente aparece primero). El clasificador de intent ya detectaba el `topic_type` como `"structural"`, pero ese dato se descartaba antes de llegar al retrieval — nada en el pipeline usaba `chunk_index`/orden del documento.

## La solución aplicada (referencia de implementación)
1. **`ClassifiedIntent` DTO** (`app/DTOs/ClassifiedIntent.php`) expone `topicType` y el helper `isStructural()`. El `OrchestratorRouter` (1 llamada LLM que decide tool + intent + topic + topic_type por turno) es quien extrae `topic_type`; `OrchestratorAgent` lo propaga sin descartarlo al construir el `ClassifiedIntent` que viaja dentro de `ToolContext`.
2. **`StructuralChunkResolver`** (`app/Services/StructuralChunkResolver.php`) resuelve estas referencias por orden documental en vez de similitud vectorial:
   - Número explícito ("problema 2.1.1") → búsqueda exacta por el marcador numerado en el texto reconstruido en orden (`chunk_index`).
   - Lenguaje ordinal ("primer", "segundo", "último", "first", "last") → cuenta los marcadores estructurales (`Problema N`, `Ejercicio N`, `Sección N`, etc.) en orden y toma el N-ésimo.
   - Devuelve `null` si no detecta ninguna referencia estructural — el llamador debe caer de vuelta a la búsqueda vectorial normal.
3. **`DocumentQATool::execute()`** (`app/Agents/Tools/DocumentQATool.php`) intenta `StructuralChunkResolver` primero cuando `$context->intent->isStructural()` es true; solo si devuelve `null` continúa con `PgvectorSimilaritySearch`.

## Cuándo aplicar esta skill
Si estás creando o modificando **cualquier tool RAG** (`DocumentQATool`, `QuizGeneratorTool`, futuros tools con `EmbeddingProvider`/`PgvectorSimilaritySearch`) y el tool puede recibir preguntas sobre elementos numerados o posicionales del documento (ejercicios, artículos, secciones, capítulos, páginas):

1. Verifica que el `OrchestratorRouter` propague `topic_type` y que `OrchestratorAgent` no lo descarte al construir el `ClassifiedIntent` de `ToolContext`.
2. Antes de correr similarity search, intenta resolver la referencia por posición/orden (reutiliza o extiende `StructuralChunkResolver`, no reimplementes vector search para esto).
3. Solo cae a similarity search cuando no hay referencia estructural detectable.
4. Si el chunking del documento (`ProcessDocumentJob`) no está alineado a los límites reales de los ítems numerados, el resolver debe reconstruir el texto en orden y buscar los marcadores directamente en el contenido — no asumir que "el N-ésimo chunk" equivale a "el N-ésimo ítem".

---
Checkpoint: ¿el agente nuevo/modificado distingue "búsqueda semántica" de "referencia posicional", o va a repetir el mismo bug?
