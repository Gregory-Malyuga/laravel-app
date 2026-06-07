<?php

namespace Domains\User\Application\Queries\FindById;

use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Shared\Bus\HandlerInterface;

readonly class FindUserByIdHandler implements HandlerInterface
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof FindUserByIdQuery);

        /** @var User $record */
        $record = $this->repository->findOrFail($message->id);

        return $record;
    }
}
