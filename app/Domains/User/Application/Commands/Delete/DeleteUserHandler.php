<?php

namespace Domains\User\Application\Commands\Delete;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserDeleted;
use Domains\User\Domain\Exceptions\UserInsufficientRoleException;
use Domains\User\Domain\Models\User;
use Shared\Bus\HandlerInterface;

readonly class DeleteUserHandler implements HandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof DeleteUserCommand);

        /** @var User $record */
        $record = $this->repository->findOrFail($message->id);

        if ($message->actor !== null && $message->actor->role !== UserRole::Admin) {
            if ($record->role === UserRole::Admin) {
                throw UserInsufficientRoleException::cannotDeleteAdmin();
            }

            if ($record->role === UserRole::Manager) {
                throw UserInsufficientRoleException::cannotManageUser();
            }
        }

        $id = $record->id;
        $this->repository->delete($record);
        UserDeleted::dispatch($id);

        return null;
    }
}
