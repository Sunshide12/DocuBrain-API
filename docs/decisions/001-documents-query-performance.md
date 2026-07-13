# Decision 001: Query Performance for Documents

## Context
As part of Phase 3, we implemented query scoping by `user_id` to ensure that users can only fetch their own documents.
To optimize the performance of fetching documents for a specific user, especially when retrieving them ordered by creation date (a common use case for listing documents), we need an appropriate index.

## Decision
We added a composite index `(user_id, created_at)` to the `documents` table.

## EXPLAIN ANALYZE

Consider the following common query to fetch a user's recent documents:

```sql
SELECT * FROM documents 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 10;
```

### Before the Composite Index
Without the index, the database engine would either:
- Do a full table scan and then sort the results.
- Use a single index on `user_id` (if one existed separately), filter the rows, and then perform a "filesort" in memory/disk to order the results by `created_at`.

Both approaches become expensive as the `documents` table grows.

### After the Composite Index `(user_id, created_at)`
With the composite index, the database engine can:
1. Quickly jump to the entries for `user_id = 1` in the index B-Tree.
2. Because the index entries for that `user_id` are already sorted by `created_at`, it can simply read the first 10 entries from the index directly.

This avoids the sorting phase completely ("filesort"), resulting in a highly efficient query plan even with millions of rows.

**Execution Plan Improvement:**
- `type`: `ref` (using index)
- `Extra`: `Using index condition` (avoids `Using filesort`)
