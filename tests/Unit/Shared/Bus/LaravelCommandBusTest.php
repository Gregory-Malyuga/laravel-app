<?php

namespace Tests\Unit\Shared\Bus;

use Illuminate\Container\Container;
use RuntimeException;
use Shared\Bus\BaseCommand;
use Shared\Bus\HandlerInterface;
use Shared\Bus\LaravelCommandBus;
use Tests\TestCase;

class LaravelCommandBusTest extends TestCase
{
    private Container $container;

    private LaravelCommandBus $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container;
        $this->bus = new LaravelCommandBus($this->container);
    }

    public function test_dispatches_command_to_resolved_handler(): void
    {
        $this->container->bind(StubHandler::class, fn () => new StubHandler);

        $result = $this->bus->dispatch(new StubCommand('payload'));

        $this->assertSame('handled:payload', $result);
    }

    public function test_resolves_handler_by_replacing_command_suffix(): void
    {
        $handler = new StubHandler;
        $this->container->bind(StubHandler::class, fn () => $handler);

        $this->bus->dispatch(new StubCommand('x'));

        $this->assertTrue($handler->called);
    }

    public function test_throws_when_class_does_not_end_with_command(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/must end with 'Command'/");

        $this->bus->dispatch(new class implements BaseCommand {});
    }

    public function test_throws_when_handler_does_not_implement_handler_interface(): void
    {
        $this->container->bind(StubHandler::class, fn () => new \stdClass);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        $this->bus->dispatch(new StubCommand('x'));
    }
}

class StubCommand implements BaseCommand
{
    public function __construct(public readonly string $payload) {}
}

class StubHandler implements HandlerInterface
{
    public bool $called = false;

    public function handle(object $message): mixed
    {
        $this->called = true;

        return 'handled:'.($message instanceof StubCommand ? $message->payload : '');
    }
}
