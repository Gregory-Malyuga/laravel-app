<?php

namespace Domains\User\Application\Commands\Create;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserCreated;
use Domains\User\Domain\Exceptions\UserInsufficientRoleException;
use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\HandlerInterface;

readonly class CreateUserHandler implements HandlerInterface
{
    public function __construct(private UserRepository $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof CreateUserCommand);

        if (
            $message->role === UserRole::Admin
            && $message->actor !== null
            && $message->actor->role !== UserRole::Admin
        ) {
            throw UserInsufficientRoleException::cannotAssignRole($message->role->value);
        }

        /** @var User $record */
        $record = $this->repository->create([
            'name' => $message->name,
            'email' => $message->email,
            'password' => Hash::make($message->password),
            'role' => $message->role,
        ]);

        UserCreated::dispatch($record);

        return $record;
    }
}
