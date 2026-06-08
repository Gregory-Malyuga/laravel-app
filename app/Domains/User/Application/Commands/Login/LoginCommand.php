<?php

namespace Domains\User\Application\Commands\Login;

use Shared\Bus\BaseCommand;

readonly class LoginCommand implements BaseCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
