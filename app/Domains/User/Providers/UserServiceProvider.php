<?php

namespace Domains\User\Providers;

use Domains\User\Application\Commands\Create\CreateUserHandler;
use Domains\User\Application\Commands\Delete\DeleteUserHandler;
use Domains\User\Application\Commands\Update\UpdateUserHandler;
use Domains\User\Application\Queries\FindById\FindUserByIdHandler;
use Domains\User\Application\Queries\ListAll\ListUsersHandler;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreateUserHandler::class);
        $this->app->bind(UpdateUserHandler::class);
        $this->app->bind(DeleteUserHandler::class);
        $this->app->bind(ListUsersHandler::class);
        $this->app->bind(FindUserByIdHandler::class);
    }

    public function boot(): void {}
}
