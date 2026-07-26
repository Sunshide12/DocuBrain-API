<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentUploaded;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates ALL cached document list pages for a user
 * whenever they upload a new document.
 *
 * HOW — Cache Versioning
 * ──────────────────────
 * The Documents resolver stores results under a versioned key:
 *   documents.user.{userId}.v{version}.page.{n}.per.{m}
 *
 * A separate counter key holds the current version:
 *   documents.user.{userId}.version  (defaults to 1)
 *
 * To invalidate ALL cached pages at once, we simply increment the version
 * counter. The resolver reads this counter before building the cache key, so
 * after incrementing it will use a NEW key (e.g. v2.*), resulting in a cache
 * miss. Fresh data is fetched from the DB and stored under the new key.
 * Old versioned keys (v1.*) expire naturally after their 5-min TTL.
 *
 * WHY NOT Redis SCAN+DEL?
 * ───────────────────────
 * Laravel applies TWO layers of Redis key prefixes:
 *   1. REDIS_PREFIX  — set in config/database.php from env('REDIS_PREFIX')
 *                      applied by the phpredis client driver to every key.
 *   2. cache.prefix  — set in config/cache.php, applied by the Cache store.
 *
 * The actual key in Redis looks like:
 *   {REDIS_PREFIX}{cache.prefix}documents.user.1.v1.page.1.per.10
 *   e.g. docubrain_docubrain-cache-documents.user.1.v1.page.1.per.10
 *
 * However, phpredis does NOT prepend REDIS_PREFIX to the MATCH argument of
 * SCAN commands. So a SCAN with match "docubrain-cache-documents.user.1.*"
 * never finds the real keys. Cache::increment() goes through the Cache facade
 * and applies both prefixes automatically — zero prefix confusion.
 */
final class InvalidateDocumentsCache
{
    public function handle(DocumentUploaded $event): void
    {
        $userId = $event->document->user_id;
        $versionKey = "documents.user.{$userId}.version";

        // Increment the version counter. If the key doesn't exist yet, this
        // creates it with value 1 and then increments to 2 — which is fine,
        // because the resolver defaults to version 1 when the key is absent.
        Cache::increment($versionKey);
    }
}
