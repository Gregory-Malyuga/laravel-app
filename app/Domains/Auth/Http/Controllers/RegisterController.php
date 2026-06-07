<?php

namespace Domains\Auth\Http\Controllers;

use Domains\Auth\Data\RegisterData;
use Domains\User\Application\Commands\Create\CreateUserCommand;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;

class RegisterController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function __invoke(RegisterData $data): JsonResponse
    {
        /** @var User $user */
        $user = $this->commands->dispatch(new CreateUserCommand(
            name: $data->name,
            email: $data->email,
            password: $data->password,
            role: UserRole::User,
        ));

        $token = $user->createToken('cp', ['cp'])->plainTextToken;

        return response()->json(['token' => $token, 'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ]], 201);
    }
}
