<?php

namespace Shared\Console\DomainGenerator\Contracts;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;

abstract class AbstractGenerator implements GeneratorInterface
{
    protected function writeFile(Filesystem $files, string $path, string $content): void
    {
        if ($files->exists($path)) {
            return;
        }

        $files->put($path, $content);
    }
}
