<?php

namespace Shared\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

class LaravelCommandBus implements CommandBusInterface
{
    public function __construct(private readonly Container $container) {}

    public function dispatch(BaseCommand $command): mixed
    {
        $commandClass = get_class($command);
        $handlerClass = preg_replace('/Command$/', 'Handler', $commandClass) ?? $commandClass;

        if ($handlerClass === $commandClass) {
            throw new RuntimeException("Command class must end with 'Command': {$commandClass}");
        }

        $handler = $this->container->make($handlerClass);

        if (! $handler instanceof HandlerInterface) {
            throw new RuntimeException("Handler {$handlerClass} must implement ".HandlerInterface::class);
        }

        return $handler->handle($command);
    }
}
