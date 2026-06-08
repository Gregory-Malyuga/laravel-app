<?php

namespace Domains\User\Domain\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserNotFoundException extends ModelNotFoundException
{
    public static function forId(int|string $id): self
    {
        return new self('Пользователь с таким идентификатором не найден');
    }
}
