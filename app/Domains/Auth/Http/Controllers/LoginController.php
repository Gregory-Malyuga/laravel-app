<?php

namespace Domains\Auth\Http\Controllers;

use Domains\Auth\Application\Commands\Login\LoginCommand;
use Domains\Auth\Data\LoginData;
use Domains\User\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;

class LoginController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function __invoke(LoginData $data): JsonResponse
    {
        /** @var User $user */
        $user = $this->commands->dispatch(new LoginCommand($data->email, $data->password));

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['token' => $token, 'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ]]);
    }
}
