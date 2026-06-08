<?php

namespace Domains\User\Application\Repositories;

use Domains\User\Application\Data\UserFilterData;
use Domains\User\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Data\PaginationData;
use Shared\Data\SortData;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /** @param array<string, mixed> $data */
    public function create(array $data): User;

    public function findOrFail(int $id): User;

    /** @return LengthAwarePaginator<int, User> */
    public function list(UserFilterData $filters, ?SortData $sort = null, ?PaginationData $pagination = null): LengthAwarePaginator;

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User;

    public function delete(User $user): void;
}
