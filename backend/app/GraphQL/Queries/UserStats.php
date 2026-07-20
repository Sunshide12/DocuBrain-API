<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Conversation;
use App\Models\Document;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Resolver for the `userStats` query.
 *
 * Returns aggregated statistics for the authenticated user:
 * - Total documents uploaded
 * - Documents with status 'ready'
 * - Total conversations started
 *
 * SECURITY: The global 'owned' scope on Document and Conversation models
 * automatically filters all queries by user_id = Auth::id(), ensuring users
 * can only see their own stats.
 */
final class UserStats
{
    /**
     * @param  null  $root
     * @param  array{}  $args  No arguments for this query
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        // The @guard directive ensures Auth::check() === true here.
        // Both Document and Conversation models apply the 'owned' global scope,
        // which adds WHERE user_id = Auth::id() to all queries automatically.

        return [
            'totalDocuments' => Document::count(),
            'documentsReady' => Document::where('status', 'ready')->count(),
            'totalConversations' => Conversation::count(),
        ];
    }
}
