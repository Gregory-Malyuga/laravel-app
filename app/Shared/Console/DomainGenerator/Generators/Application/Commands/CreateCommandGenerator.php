<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class CreateCommandGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $params = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $phpType = $def['nullable'] ? "?{$def['phpType']}" : $def['phpType'];
            $default = $def['nullable'] ? ' = null' : '';
            $params .= "        public {$phpType} \${$fieldName}{$default},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Create;

        use Shared\\Bus\\BaseCommand;

        readonly class Create{$ctx->name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Create/Create{$ctx->name}Command.php", $content);
    }
}
