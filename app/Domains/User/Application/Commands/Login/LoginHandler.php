<?php

namespace Domains\User\Application\Commands\Login;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Exceptions\InvalidCredentialsException;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\HandlerInterface;

readonly class LoginHandler implements HandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): User
    {
        assert($message instanceof LoginCommand);

        $user = $this->repository->findByEmail($message->email);

        if ($user === null || ! Hash::check($message->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }
}
