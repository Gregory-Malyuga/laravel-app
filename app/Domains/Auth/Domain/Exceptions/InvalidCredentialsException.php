<?php

namespace Domains\Auth\Domain\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Неверный email или пароль.');
    }
}
