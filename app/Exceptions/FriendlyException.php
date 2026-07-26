<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;

/**
 * A user-facing error safe to display verbatim in the GraphQL response, regardless
 * of APP_DEBUG. Use for validation-style failures the user needs to act on
 * (e.g. "select a document first"), never for internal/unexpected errors.
 */
class FriendlyException extends Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
