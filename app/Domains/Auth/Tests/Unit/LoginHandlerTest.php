<?php

namespace Domains\Auth\Tests\Unit;

use Domains\Auth\Application\Commands\Login\LoginCommand;
use Domains\Auth\Application\Commands\Login\LoginHandler;
use Domains\Auth\Domain\Exceptions\InvalidCredentialsException;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;

class LoginHandlerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_user_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
            'role' => UserRole::Admin,
        ]);

        $handler = new LoginHandler(app(UserRepository::class));
        $result = $handler->handle(new LoginCommand('admin@test.com', 'secret'));

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_throws_invalid_credentials_for_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('correct'),
            'role' => UserRole::Admin,
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $handler = new LoginHandler(app(UserRepository::class));
        $handler->handle(new LoginCommand('admin@test.com', 'wrong'));
    }

    public function test_throws_invalid_credentials_for_unknown_email(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $handler = new LoginHandler(app(UserRepository::class));
        $handler->handle(new LoginCommand('nobody@test.com', 'anything'));
    }
}
