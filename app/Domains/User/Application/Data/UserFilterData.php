<?php

namespace Domains\User\Application\Data;

use Spatie\LaravelData\Data;

class UserFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $role = null,
    ) {}
}
