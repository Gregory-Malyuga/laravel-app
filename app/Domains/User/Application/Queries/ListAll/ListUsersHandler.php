<?php

namespace Domains\User\Application\Queries\ListAll;

use Domains\User\Infrastructure\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Bus\HandlerInterface;

readonly class ListUsersHandler implements HandlerInterface
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof ListUsersQuery);

        /** @var LengthAwarePaginator<int, mixed> $result */
        $result = $this->repository->list(
            $message->filters,
            $message->sort,
            $message->pagination,
        );

        return $result;
    }
}
