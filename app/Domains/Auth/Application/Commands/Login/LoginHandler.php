<?php

namespace Domains\Auth\Application\Commands\Login;

use Domains\Auth\Domain\Exceptions\InvalidCredentialsException;
use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\HandlerInterface;

readonly class LoginHandler implements HandlerInterface
{
    public function __construct(private UserRepository $repository) {}

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
