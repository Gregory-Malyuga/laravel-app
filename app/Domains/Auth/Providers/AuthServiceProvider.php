<?php

namespace Domains\Auth\Providers;

use Domains\Auth\Application\Commands\Login\LoginHandler;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginHandler::class);
    }

    public function boot(): void {}
}
