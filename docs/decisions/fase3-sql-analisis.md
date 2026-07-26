# Fase 3 — SQL Avanzado: Análisis con EXPLAIN ANALYZE

> **Cómo usar este documento**
> Conéctate a la base de datos PostgreSQL del proyecto:
> ```bash
> docker compose exec postgres psql -U docubrain -d docubrain
> ```
> Luego copia y pega las queries de este documento para ver los planes de ejecución reales.

---

## 1. Contexto: ¿Qué es EXPLAIN ANALYZE?

`EXPLAIN` le pregunta al optimizador de PostgreSQL: *"¿Cómo ejecutarías esta query?"*
`EXPLAIN ANALYZE` lo hace de verdad y mide el tiempo real.

La salida más importante a leer:

| Campo | Qué significa |
|---|---|
| `Seq Scan` | Recorre toda la tabla fila por fila (O(n)) |
| `Index Scan` | Usa un B-tree para ir directo (O(log n)) |
| `Bitmap Heap Scan` | Intermedio: marca páginas con el índice, luego las lee |
| `cost=X..Y` | X = costo de arranque, Y = costo total (unidades arbitrarias del planner) |
| `rows=N` | Estimación de filas que devuelve el nodo |
| `actual time=X..Y` | Tiempo real en milisegundos (solo con ANALYZE) |
| `loops=N` | Cuántas veces se ejecutó este nodo (importante en Nested Loops) |

---

## 2. Análisis: Query de listado de documentos por usuario

### La query que lanza Laravel

```sql
SELECT *
FROM documents
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 10;
```

### Sin el índice compuesto `idx_documents_user_created`

```sql
EXPLAIN ANALYZE
SELECT *
FROM documents
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 10;
```

**Plan esperado con tabla pequeña (<1000 filas):**
```
Limit  (cost=35.52..35.54 rows=10 width=200) (actual time=0.432..0.434 rows=10 loops=1)
  ->  Sort  (cost=35.52..36.02 rows=200 width=200) (actual time=0.431..0.432 rows=10 loops=1)
        Sort Key: created_at DESC
        Sort Method: top-N heapsort  Memory: 27kB
        ->  Seq Scan on documents  (cost=0.00..31.00 rows=200 width=200) (actual time=0.008..0.389 rows=200 loops=1)
              Filter: (user_id = 1)
              Rows Removed by Filter: 50
Planning Time: 0.234 ms
Execution Time: 0.456 ms
```

**Lo que pasa:**
- PostgreSQL hace un **Seq Scan** (lee TODOS los documentos) y luego **Sort** por `created_at`.
- Con pocas filas esto es rápido, pero con 100.000 documentos escalaría mal.

### Con el índice `idx_documents_user_created`

La migración de Fase 3 agrega:
```sql
CREATE INDEX idx_documents_user_created ON documents (user_id, created_at DESC);
```

**Plan esperado:**
```
Limit  (cost=0.28..1.42 rows=10 width=200) (actual time=0.021..0.028 rows=10 loops=1)
  ->  Index Scan using idx_documents_user_created on documents  (cost=0.28..22.90 rows=200 width=200) (actual time=0.020..0.026 rows=10 loops=1)
        Index Cond: (user_id = 1)
Planning Time: 0.156 ms
Execution Time: 0.042 ms
```

**Lo que pasa con el índice:**
- **Index Scan** directo: PostgreSQL va al B-tree, encuentra los documentos del usuario ya ordenados por `created_at`.
- No hay Sort separado — el índice **ya los devuelve en orden**.
- El `cost` baja de `35.52` a `1.42` — una mejora sustancial a escala.

> **Nota:** Con muy pocas filas, PostgreSQL puede elegir el Seq Scan de todas formas —
> el planner tiene en cuenta el costo de leer el índice + las páginas del heap.
> El índice se activa con certeza cuando tienes >1000 filas por usuario.
> Usa `SET enable_seqscan = OFF;` para forzar el Index Scan durante el aprendizaje.

---

## 3. Análisis: Query de chunks por documento

### La query que lanza Laravel

```sql
SELECT *
FROM document_chunks
WHERE document_id = 1
ORDER BY chunk_index ASC;
```

### Sin el índice compuesto `idx_chunks_document_order`

```sql
EXPLAIN ANALYZE
SELECT *
FROM document_chunks
WHERE document_id = 1
ORDER BY chunk_index ASC;
```

**Plan sin índice compuesto:**
```
Sort  (cost=14.34..14.59 rows=100 width=500)
      Sort Key: chunk_index
  ->  Index Scan using document_chunks_document_id_index on document_chunks
        Index Cond: (document_id = 1)
```

El FK de `document_id` ya tiene un índice, así que evita el Seq Scan.
Pero **todavía hace un Sort separado** para el `ORDER BY chunk_index`.

### Con el índice compuesto `idx_chunks_document_order`

```sql
CREATE INDEX idx_chunks_document_order ON document_chunks (document_id, chunk_index);
```

**Plan con índice compuesto:**
```
Index Scan using idx_chunks_document_order on document_chunks
      Index Cond: (document_id = 1)
```

- **Sin Sort**: el índice compuesto `(document_id, chunk_index)` ya almacena los chunks del documento ordenados por `chunk_index`.
- Un solo Index Scan, sin operación de Sort adicional.

---

## 4. Problema N+1 — Demostración

### ¿Qué es N+1?

Cuando pedimos documentos con sus chunks y sus usuarios:

```graphql
query {
  documents {
    data {
      title
      user { name }
      chunks { id content }
    }
  }
}
```

**Sin eager loading** (el problema), Lighthouse/Eloquent hace:
```sql
-- Query 1: Obtiene los N documentos
SELECT * FROM documents WHERE user_id = ? LIMIT 10;

-- Queries 2..N+1: Una query POR CADA documento para el usuario
SELECT * FROM users WHERE id = 1;
SELECT * FROM users WHERE id = 1;  -- mismo usuario, repetido N veces!
SELECT * FROM users WHERE id = 1;

-- Queries N+2..2N+1: Una query POR CADA documento para los chunks
SELECT * FROM document_chunks WHERE document_id = 1;
SELECT * FROM document_chunks WHERE document_id = 2;
SELECT * FROM document_chunks WHERE document_id = 3;
-- ... N queries más
```

Con 10 documentos = **21 queries**. Con 100 documentos = **201 queries**.

### La solución: Eager Loading con `@hasMany` / `@belongsTo`

Lighthouse usa un **BatchLoader** cuando declaras relaciones con sus directivas:

```graphql
type Document {
    user: User! @belongsTo      # ← Lighthouse agrupará en WHERE id IN (...)
    chunks: [DocumentChunk!]! @hasMany  # ← Lighthouse agrupará en WHERE document_id IN (...)
}
```

**Con eager loading** (el fix):
```sql
-- Query 1: Obtiene los N documentos
SELECT * FROM documents WHERE user_id = ? LIMIT 10;

-- Query 2: UN SOLO query para todos los usuarios (batch)
SELECT * FROM users WHERE id IN (1, 1, 1, 1, 1);
-- (PostgreSQL lo optimiza a IN (1) pero la idea es N→1)

-- Query 3: UN SOLO query para todos los chunks (batch)
SELECT * FROM document_chunks WHERE document_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
```

Con 10 documentos = **3 queries**. Con 100 documentos = **3 queries**. Constante.

### ¿Cómo verificarlo en el código?

En `DocumentChunkTest::test_documents_with_user_and_chunks_does_not_cause_n_plus_1()`:

```php
DB::enableQueryLog();
// ... ejecutar la GraphQL query
$queries = DB::getQueryLog();
DB::disableQueryLog();

// Con @hasMany + @belongsTo: ≤ 6 queries (1 docs + 1 users + 1 chunks + overhead de caché)
// Sin eager loading: 1 + N + N = 11 queries para 5 documentos
$this->assertLessThanOrEqual(6, count($queries));
```

---

## 5. Decisión: ¿Cuándo agregar índices HNSW para embeddings?

> Esto se decide en **Fase 8**, no aquí.

En Fase 8, cuando agreguemos `embedding vector(1536)` a `document_chunks`,
la búsqueda de similitud coseno sin índice hace:

```sql
SELECT * FROM document_chunks
ORDER BY embedding <=> '[0.1, 0.2, ...]'  -- cosine distance
LIMIT 5;
```

Esto es un **Seq Scan vectorial** — compara el vector de la query contra TODOS los chunks.
Con <10.000 chunks es instantáneo. El índice HNSW se agrega cuando escale:

```sql
-- Se agrega en Fase 8 o posterior cuando los chunks superen ~10.000
CREATE INDEX ON document_chunks USING hnsw (embedding vector_cosine_ops);
```

---

## 6. Comandos útiles para explorar

```bash
# Ver todos los índices de la tabla documents
SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'documents';

# Ver el tamaño de los índices
SELECT indexname, pg_size_pretty(pg_relation_size(indexname::regclass)) AS size
FROM pg_indexes WHERE tablename IN ('documents', 'document_chunks');

# Ver estadísticas de uso de índices (cuántas veces se usó cada uno)
SELECT indexrelname, idx_scan, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes
WHERE relname IN ('documents', 'document_chunks');

# Forzar el uso de índice (deshabilita Seq Scan para ver el plan alternativo)
SET enable_seqscan = OFF;
EXPLAIN ANALYZE SELECT * FROM documents WHERE user_id = 1 ORDER BY created_at DESC LIMIT 10;
SET enable_seqscan = ON;  -- siempre restaurar al terminar
```
