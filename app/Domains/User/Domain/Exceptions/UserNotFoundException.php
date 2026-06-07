<?php

namespace Domains\User\Domain\Exceptions;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public static function forId(int|string $id): self
    {
        return new self("User not found: {$id}");
    }
}
