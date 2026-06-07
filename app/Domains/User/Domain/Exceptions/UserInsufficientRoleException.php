<?php

namespace Domains\User\Domain\Exceptions;

use RuntimeException;

class UserInsufficientRoleException extends RuntimeException
{
    public static function cannotAssignRole(string $role): self
    {
        return new self("Insufficient role to assign role: {$role}");
    }

    public static function cannotDeleteAdmin(): self
    {
        return new self('Insufficient role to delete an admin user');
    }

    public static function cannotManageUser(): self
    {
        return new self('Insufficient role to manage this user');
    }
}
