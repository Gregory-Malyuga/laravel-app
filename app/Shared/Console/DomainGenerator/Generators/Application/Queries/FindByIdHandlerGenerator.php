<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Queries;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Context\DomainContext;

class FindByIdHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Queries\\FindById;

        use Shared\\Bus\\QueryHandlerInterface;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Infrastructure\\Repositories\\{$ctx->name}Repository;

        readonly class Find{$ctx->name}ByIdHandler implements QueryHandlerInterface
        {
            public function __construct(private readonly {$ctx->name}Repository \$repository) {}

            public function handle(object \$message): {$ctx->name}
            {
                assert(\$message instanceof Find{$ctx->name}ByIdQuery);

                /** @var {$ctx->name} \$record */
                \$record = \$this->repository->findOrFail(\$message->id);

                return \$record;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Queries/FindById/Find{$ctx->name}ByIdHandler.php", $content);
    }
}
