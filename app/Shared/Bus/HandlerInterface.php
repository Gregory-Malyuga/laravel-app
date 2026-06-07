<?php

namespace Shared\Bus;

interface HandlerInterface
{
    public function handle(object $message): mixed;
}
