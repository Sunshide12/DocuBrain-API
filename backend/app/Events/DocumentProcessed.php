<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a Document has finished processing (success or failure).
 * Listeners can use this to invalidate caches so the frontend sees the final status.
 */
final class DocumentProcessed
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
    ) {}
}
