<?php

namespace Domains\User\Application\Commands\Logout;

use Laravel\Sanctum\PersonalAccessToken;
use Shared\Bus\HandlerInterface;

readonly class LogoutHandler implements HandlerInterface
{
    public function handle(object $message): null
    {
        assert($message instanceof LogoutCommand);

        PersonalAccessToken::whereKey($message->tokenId)->delete();

        return null;
    }
}
