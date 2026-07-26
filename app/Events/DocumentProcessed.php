<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

final class DocumentProcessed
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
    ) {}
}
