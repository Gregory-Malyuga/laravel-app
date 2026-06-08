<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Login\LoginCommand;
use Domains\User\Application\Data\AuthTokenData;
use Domains\User\Application\Data\AuthUserData;
use Domains\User\Application\Data\LoginData;
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

        return response()->json(new AuthTokenData($token, AuthUserData::from($user)));
    }
}
