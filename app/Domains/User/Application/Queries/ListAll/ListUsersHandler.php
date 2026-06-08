<?php

namespace Domains\User\Application\Queries\ListAll;

use Domains\User\Application\Data\UserFilterData;
use Domains\User\Application\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Bus\HandlerInterface;

readonly class ListUsersHandler implements HandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): mixed
    {
        assert($message instanceof ListUsersQuery);

        assert($message->filters instanceof UserFilterData);

        /** @var LengthAwarePaginator<int, mixed> $result */
        $result = $this->repository->list(
            $message->filters,
            $message->sort,
            $message->pagination,
        );

        return $result;
    }
}
