<?php

namespace Domains\User\Infrastructure\Repositories;

use Domains\User\Domain\Models\User;
use Shared\Repository\BaseRepository;

class UserRepository extends BaseRepository
{
    protected string $model = User::class;

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        return $user;
    }
}
