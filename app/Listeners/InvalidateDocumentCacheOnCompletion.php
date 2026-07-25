<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentProcessed;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates ALL cached document list pages for a user
 * whenever their document finishes processing.
 *
 * This ensures the frontend doesn't see a stale "pending" status.
 */
final class InvalidateDocumentCacheOnCompletion
{
    public function handle(DocumentProcessed $event): void
    {
        $userId      = $event->document->user_id;
        $versionKey  = "documents.user.{$userId}.version";

        Cache::increment($versionKey);
    }
}
