<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a Document record is created in the DB.
 * Listeners can use this to invalidate caches, send notifications, etc.
 */
final class DocumentUploaded
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
    ) {}
}
