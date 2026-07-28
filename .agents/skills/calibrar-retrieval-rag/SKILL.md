---
name: calibrar-retrieval-rag
description: How to tune similarity thresholds and chunk selection from measurements instead of guessing. Use whenever answers miss content that is in the document, or cite passages that are irrelevant.
---

# Calibrating RAG Retrieval

**Critical rule: measure the score distribution before changing any threshold.**
Cosine similarity values are not portable — they depend on the embedding model,
the document and the phrasing. A number that sounds strict ("0.75, only confident
matches") can reject everything, and one that sounds permissive can filter nothing.

## 1. Measure first

For a real question against a real document, print the distribution:

```php
$v = (new \Pgvector\Laravel\Vector($emb->embed($pregunta)))->__toString();
\DB::select('SELECT 1-(embedding <=> ?) s FROM document_chunks WHERE document_id=? ORDER BY embedding <=> ? LIMIT 10', [$v,$id,$v]);
```

Ask two questions of the output:

- **How many chunks clear the threshold?** Measured with `text-embedding-3-small`
  on this project, a 0.2 threshold passed 82–98% of every document. It was not
  filtering — the `ORDER BY ... LIMIT` decided every answer on its own.
- **How wide is the gap between the top score and the tenth?** On a novel the top
  ten fit inside 0.05, so ranking was close to arbitrary among them. On a paper
  the top two scored 0.65/0.63 and the rest dropped to 0.46 — there the ranking
  carries real signal.

Relevant passages typically score **0.33–0.55**, not 0.8. Do not assume a
"confident match" is a high absolute number.

## 2. Prefer a relative cutoff to an absolute one

Because the scale shifts per document and per question, cut relative to the best
match — keep chunks within a margin of the top score — and keep the absolute
threshold only as a floor for the case where nothing matches. See
`PgvectorSimilaritySearch::searchAdaptive()`.

## 3. A guard must never be stricter than the retrieval it protects

If a pre-check decides "this topic is not in the document" using a **higher**
threshold than the retrieval that follows, it rejects questions the retrieval
would have answered perfectly. Any such guard must read the same configured
threshold, and must never require more matching chunks than the document has —
a short PDF holds everything in one chunk and would fail a "needs 2 matches"
rule forever.

## 4. Some questions must not use similarity at all

- **Document-level** ("¿de qué trata esto?", "resume el documento"): no chunk is
  more "about" the document than another, so similarity returns whatever shares
  vocabulary with the phrasing. Use the **opening chunks** (title, abstract,
  index). Signal: the router reports `topic: null`.
- **Ordinal or numbered** ("el tercer ejercicio", "artículo 11", "problema 2.1"):
  resolve by position in the document. See `retrieval-estructural-ordinal`.

## 5. Chunk hygiene at ingestion beats filtering at query time

Page remnants — slide titles, photo captions, indexes — are short and win
retrieval because **cosine similarity is inflated on very short texts**. Dropping
them during ingestion (`ProcessDocumentJob::dropLowInformationChunks`) also saves
embedding cost. Two traps:

- **Count words by whitespace, not with the chunker's counter.** Token soup like
  `<pad> <EOS> .` inflates a punctuation-splitting counter to more than twice its
  real length and slips past the floor.
- **Verify what you are deleting** before keeping the filter. Print the dropped
  chunks: dropping "PHOTOS & CHARTS" is right, dropping table rows is a
  regression on exactly the documents where numbers matter.

## 6. Beware of caches while calibrating

`DocumentQATool` caches answers by document + normalised question. If you retune
and re-ask the same wording, you may be reading the old answer.

```bash
docker exec <app> php artisan cache:clear
```

---
Checkpoint: did you measure the distribution, confirm the change on the exact
question that failed, and check the previous behaviour was not cached?
