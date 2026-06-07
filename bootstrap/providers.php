<?php

use Domains\Auth\Providers\AuthServiceProvider;
use Domains\User\Providers\UserServiceProvider;
use Shared\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    UserServiceProvider::class,
    Domains\StubGen\Providers\StubGenServiceProvider::class,
];
