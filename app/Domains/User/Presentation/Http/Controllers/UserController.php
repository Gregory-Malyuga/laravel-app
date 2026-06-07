<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Create\CreateUserCommand;
use Domains\User\Application\Commands\Delete\DeleteUserCommand;
use Domains\User\Application\Commands\Update\UpdateUserCommand;
use Domains\User\Application\Data\UserData;
use Domains\User\Application\Queries\FindById\FindUserByIdQuery;
use Domains\User\Application\Queries\ListAll\ListUsersQuery;
use Domains\User\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;
use Shared\Bus\QueryBusInterface;
use Spatie\LaravelData\PaginatedDataCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = $this->queries->ask(ListUsersQuery::fromRequest($request));

        return response()->json(UserData::collect($paginator, PaginatedDataCollection::class));
    }

    public function show(int $id): JsonResponse
    {
        /** @var User $record */
        $record = $this->queries->ask(new FindUserByIdQuery($id));

        return response()->json(UserData::from($record));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:8']]);

        $dto = UserData::from($request);

        /** @var User|null $actor */
        $actor = $request->user();

        /** @var User $record */
        $record = $this->commands->dispatch(new CreateUserCommand(
            name: $dto->name,
            email: $dto->email,
            password: $dto->password ?? '',
            role: $dto->role,
            actor: $actor,
        ));

        return response()->json(UserData::from($record), 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $dto = UserData::from($request);

        /** @var User|null $actor */
        $actor = $request->user();

        /** @var User $record */
        $record = $this->commands->dispatch(new UpdateUserCommand(
            id: $id,
            name: $dto->name,
            email: $dto->email,
            password: $dto->password,
            role: $dto->role,
            actor: $actor,
        ));

        return response()->json(UserData::from($record));
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();

        $this->commands->dispatch(new DeleteUserCommand($id, $actor));

        return response()->json(null, 204);
    }
}
