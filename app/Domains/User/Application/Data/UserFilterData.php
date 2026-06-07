<?php

namespace Domains\User\Application\Data;

use Shared\Data\BaseFilterData;

class UserFilterData extends BaseFilterData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $role = null,
    ) {}
}
