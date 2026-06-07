<?php

namespace Domains\Auth\Domain\Exceptions;

use RuntimeException;

class AuthForbiddenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Доступ запрещён.');
    }
}
