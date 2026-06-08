<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Register\RegisterCommand;
use Domains\User\Application\Data\AuthTokenData;
use Domains\User\Application\Data\AuthUserData;
use Domains\User\Application\Data\RegisterData;
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
        $user = $this->commands->dispatch(new RegisterCommand($data->name, $data->email, $data->password));

        $token = $user->createToken('cp')->plainTextToken;

        return response()->json(new AuthTokenData($token, AuthUserData::from($user)), 201);
    }
}
