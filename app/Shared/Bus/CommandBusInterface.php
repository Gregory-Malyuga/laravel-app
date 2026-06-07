<?php

namespace Shared\Bus;

interface CommandBusInterface
{
    public function dispatch(BaseCommand $command): mixed;
}
