<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Context\DomainContext;

class UpdateCommandGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $params = "        public int \$id,\n";
        foreach ($ctx->fields as $fieldName => $def) {
            $phpType = $def['nullable'] ? "?{$def['phpType']}" : $def['phpType'];
            $default = $def['nullable'] ? ' = null' : '';
            $params .= "        public {$phpType} \${$fieldName}{$default},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Update;

        use Shared\\Bus\\BaseCommand;

        readonly class Update{$ctx->name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Update/Update{$ctx->name}Command.php", $content);
    }
}
