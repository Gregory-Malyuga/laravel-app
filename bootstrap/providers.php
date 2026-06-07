<?php

use Domains\Auth\Providers\AuthServiceProvider;
use Domains\StubGen\Providers\StubGenServiceProvider;
use Domains\User\Providers\UserServiceProvider;
use Shared\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    UserServiceProvider::class,
    StubGenServiceProvider::class,
];
