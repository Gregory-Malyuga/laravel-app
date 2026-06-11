<?php

namespace Shared\Console\DomainGenerator\Generators\Infrastructure;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Context\DomainContext;

class RepositoryGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $esImports = $ctx->withElasticsearch
            ? "\nuse Shared\\Elasticsearch\\ElasticsearchSearchable;\nuse Shared\\Elasticsearch\\InteractsWithElasticsearch;"
            : '';
        $esInterface = $ctx->withElasticsearch ? ' implements ElasticsearchSearchable' : '';
        $esTrait = $ctx->withElasticsearch ? "\n    use InteractsWithElasticsearch;\n" : '';

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Infrastructure\\Repositories;

        use Shared\\Repository\\BaseRepository;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};{$esImports}

        class {$ctx->name}Repository extends BaseRepository{$esInterface}
        {{$esTrait}
            protected string \$model = {$ctx->name}::class;
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Infrastructure/Repositories/{$ctx->name}Repository.php", $content);
    }
}
