<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Shared\Http\Data\BaseData;

class UserData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly ?string $password = null,
        public readonly ?int $id = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        $roles = implode(',', UserRole::values());

        return [
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['required', 'string', "in:{$roles}"],
            'created_at' => ['nullable', 'string'],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
