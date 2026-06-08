<?php

namespace Domains\User\Application\Queries\FindById;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Models\User;
use Shared\Bus\HandlerInterface;

readonly class FindUserByIdHandler implements HandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof FindUserByIdQuery);

        /** @var User $record */
        $record = $this->repository->findOrFail($message->id);

        return $record;
    }
}
