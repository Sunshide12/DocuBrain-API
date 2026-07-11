<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Document;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Resolver for the `documents` query.
 *
 * CACHING STRATEGY — Cache Versioning
 * ─────────────────────────────────────
 * We cache paginated results in a versioned key:
 *   documents.user.{userId}.v{version}.page.{page}.per.{first}
 *
 * A per-user "version" counter lives in Cache under:
 *   documents.user.{userId}.version   (defaults to 1)
 *
 * INVALIDATION
 * ────────────
 * When a new document is uploaded, `DocumentUploaded` fires →
 * `InvalidateDocumentsCache` calls Cache::increment('documents.user.{userId}.version').
 * This changes the version from 1 → 2, so all subsequent queries use a NEW cache
 * key (v2.*). Old v1.* keys expire naturally on their 5-min TTL.
 *
 * WHY versioning instead of Redis SCAN+DEL?
 *   • Laravel applies TWO key prefixes: REDIS_PREFIX (driver level) + cache.prefix
 *     (store level). phpredis does NOT prepend REDIS_PREFIX to SCAN MATCH patterns,
 *     so the pattern never matches the real keys in Redis.
 *   • Incrementing a counter is O(1), works on any cache backend (array, Redis, etc.)
 *     and avoids all prefix complexity.
 *
 * NOTE ON toArray()
 * We cache plain PHP arrays (not Eloquent models or LengthAwarePaginator objects)
 * because Redis serializes them as PHP objects. Deserializing complex objects requires
 * the class to be loaded first — which may not be the case, causing a fatal error.
 * (See: app/Listeners/InvalidateDocumentsCache.php)
 */
final class Documents
{
    /**
     * @param  null  $root
     * @param  array{first?: int, page?: int}  $args
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        /** @var \App\Models\User $user */
        $user   = $context->user();
        $first  = max(1, (int) ($args['first'] ?? 10));
        $page   = max(1, (int) ($args['page'] ?? 1));
        $userId = $user->id;


        // Cache versioning strategy:
        // We read a per-user "version" counter from cache. The counter is stored
        // under "documents.user.{userId}.version" and defaults to 1.
        // The actual paginated data is stored under a key that includes this version.
        //
        // WHY versioning instead of SCAN+DEL?
        //   • Redis SCAN patterns don't interact well with Laravel's two-layer prefix
        //     system (REDIS_PREFIX + cache.prefix). The final key name in Redis is
        //     "{REDIS_PREFIX}{cache.prefix}documents.user...." but phpredis does NOT
        //     auto-prepend the prefix to the MATCH pattern argument, causing mismatches.
        //   • Incrementing a counter is O(1) and works identically on the array driver
        //     (tests), the Redis driver (Docker/production), and any other cache backend.
        //   • Old versioned keys just expire after their TTL — no explicit deletion needed.
        $version  = (int) Cache::get("documents.user.{$userId}.version", 1);
        $cacheKey = "documents.user.{$userId}.v{$version}.page.{$page}.per.{$first}";

        // IMPORTANT: We cache the final plain array, NOT the LengthAwarePaginator object.
        // Caching an Eloquent paginator in Redis causes an unserialize() failure on the
        // second request because LengthAwarePaginator isn't loaded before PHP tries to
        // deserialize it. Caching a plain array of primitives has no such issue.
        return Cache::remember(
            key:     $cacheKey,
            ttl:     300,
            callback: function () use ($userId, $first, $page): array {
                $paginator = Document::where('user_id', $userId)
                    ->latest()
                    ->paginate($first, ['*'], 'page', $page);

                // Convert Eloquent models to plain arrays so Redis can safely
                // serialize/deserialize without needing model class definitions.
                return [
                    'data'          => array_map(
                        fn (Document $doc) => $doc->toArray(),
                        $paginator->items(),
                    ),
                    'paginatorInfo' => [
                        'total'        => $paginator->total(),
                        'perPage'      => $paginator->perPage(),
                        'currentPage'  => $paginator->currentPage(),
                        'lastPage'     => $paginator->lastPage(),
                        'hasMorePages' => $paginator->hasMorePages(),
                    ],
                ];
            },
        );
    }
}
