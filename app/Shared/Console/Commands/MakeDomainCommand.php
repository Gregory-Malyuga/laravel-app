<?php

namespace Shared\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain
        {name : Domain name in PascalCase, optionally nested with / (e.g. Store or Users/User)}
        {fields?* : Field definitions as field:type (e.g. name:string email:email). Omit id/created_at/updated_at.}
        {--with-cache-warmer : Generate a CacheWarmer class}
        {--with-elasticsearch : Add ElasticsearchSearchable to Repository}';

    protected $description = 'Scaffold a full working CRUD domain with CQRS, tests, routes and OpenAPI docs.';

    private string $ns = '';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rawInput = str_replace('\\', '/', (string) $this->argument('name'));
        $segments = array_values(array_filter(array_map('ucfirst', explode('/', $rawInput))));
        $name = (string) array_pop($segments);
        $parentParts = $segments; // e.g. ['Users'] for Users/User

        $domainSubPath = $parentParts ? implode('/', $parentParts).'/'.$name : $name;
        $this->ns = 'Domains\\'.($parentParts ? implode('\\', $parentParts).'\\' : '').$name;

        $table = Str::snake(Str::plural($name));
        $parentPath = $parentParts
            ? implode('/', array_map(fn ($p) => Str::kebab($p), $parentParts)).'/'
            : '';
        $plural = $parentPath.Str::kebab(Str::plural($name));

        /** @var list<string> $rawFields */
        $rawFields = (array) $this->argument('fields');
        $fields = $this->parseFields($rawFields);

        $basePath = base_path("app/Domains/{$domainSubPath}");
        $unitTestPath = base_path("app/Domains/{$domainSubPath}/Tests/Unit");
        $featureTestPath = base_path("app/Domains/{$domainSubPath}/Tests/Feature");

        $this->createDirectories($basePath, $unitTestPath, $featureTestPath);
        $this->generateFiles($name, $table, $plural, $fields, $basePath, $unitTestPath, $featureTestPath);
        $this->generateMigration($name, $table, $fields);
        $this->registerProvider($name);

        $this->call('migrate');

        $this->formatGenerated($name, $basePath);

        if (class_exists('Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider')) {
            $this->call('ide-helper:models', ['--nowrite' => true]);
        }

        $this->info("Domain <comment>{$name}</comment> generated. Register provider in bootstrap/providers.php if not done automatically.");

        return self::SUCCESS;
    }

    // ── Post-generation formatting ─────────────────────────────────────────────

    private function formatGenerated(string $name, string $domainPath): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $pint = base_path('vendor/bin/pint');
        if (! $this->files->exists($pint)) {
            return;
        }

        $migGlob = database_path('migrations/*_create_'.strtolower(str_replace([' ', '-'], '_', (string) preg_replace('/(?<=\w)([A-Z])/', '_$1', $name))).'*_table.php');

        $domainExists = $this->files->isDirectory($domainPath) ? [$domainPath] : [];
        $migFiles = $this->files->glob($migGlob);
        $all = array_merge($domainExists, $migFiles);

        if (empty($all)) {
            return;
        }

        /** @var list<string> $all */
        $cmd = $pint.' '.implode(' ', array_map(fn (string $f): string => escapeshellarg($f), $all)).' 2>/dev/null';
        exec($cmd);

        $this->line('  <info>Formatted:</info> generated files with Pint');
    }

    // ── Field parsing ──────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $rawFields
     * @return array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>
     */
    private function parseFields(array $rawFields): array
    {
        $skip = ['id', 'created_at', 'updated_at'];
        $result = [];

        foreach ($rawFields as $raw) {
            $parts = explode(':', $raw, 2);
            $fieldName = $parts[0];
            $explicitType = $parts[1] ?? '';

            if (in_array($fieldName, $skip, true)) {
                continue;
            }

            $type = $explicitType !== '' ? $explicitType : $this->inferType($fieldName);
            $result[$fieldName] = $this->typeDefinition($fieldName, $type);
        }

        return $result;
    }

    private function inferType(string $fieldName): string
    {
        return match (true) {
            str_ends_with($fieldName, 'email') => 'email',
            str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel') => 'phone',
            str_ends_with($fieldName, '_id') => 'integer',
            str_ends_with($fieldName, '_at') => 'timestamp',
            str_ends_with($fieldName, '_date') => 'date',
            str_ends_with($fieldName, '_count') || str_ends_with($fieldName, '_amount') => 'integer',
            str_ends_with($fieldName, '_price') || str_ends_with($fieldName, '_cost') => 'float',
            str_starts_with($fieldName, 'is_') || str_starts_with($fieldName, 'has_') => 'boolean',
            default => 'string',
        };
    }

    /**
     * @return array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}
     */
    private function typeDefinition(string $fieldName, string $type): array
    {
        $faker = $this->fakerForField($fieldName, $type);

        return match ($type) {
            'integer', 'int' => ['phpType' => 'int', 'nullable' => false, 'migration' => 'integer', 'decimal' => false, 'faker' => $faker, 'rules' => ['integer']],
            'float', 'decimal', 'numeric' => ['phpType' => 'float', 'nullable' => false, 'migration' => 'decimal', 'decimal' => true, 'faker' => $faker, 'rules' => ['numeric']],
            'boolean', 'bool' => ['phpType' => 'bool', 'nullable' => false, 'migration' => 'boolean', 'decimal' => false, 'faker' => $faker, 'rules' => ['boolean']],
            'text' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'text', 'decimal' => false, 'faker' => $faker, 'rules' => ['string']],
            'email' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'email', 'max:255']],
            'phone' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'max:30']],
            'date' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'date', 'decimal' => false, 'faker' => $faker, 'rules' => ['date']],
            'timestamp', 'datetime' => ['phpType' => 'string', 'nullable' => false, 'migration' => 'timestamp', 'decimal' => false, 'faker' => $faker, 'rules' => ['date']],
            'json', 'array' => ['phpType' => 'array', 'nullable' => true, 'migration' => 'json', 'decimal' => false, 'faker' => $faker, 'rules' => ['nullable', 'array']],
            default => ['phpType' => 'string', 'nullable' => false, 'migration' => 'string', 'decimal' => false, 'faker' => $faker, 'rules' => ['string', 'max:255']],
        };
    }

    private function fakerForField(string $fieldName, string $type): string
    {
        return match (true) {
            str_contains($fieldName, 'first_name') => 'fake()->firstName()',
            str_contains($fieldName, 'last_name') => 'fake()->lastName()',
            str_contains($fieldName, 'name') && ! str_contains($fieldName, 'user') => 'fake()->name()',
            str_ends_with($fieldName, 'email') => 'fake()->unique()->safeEmail()',
            str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel') => 'fake()->phoneNumber()',
            str_contains($fieldName, 'address') => 'fake()->address()',
            str_contains($fieldName, 'city') => 'fake()->city()',
            str_contains($fieldName, 'country') => 'fake()->country()',
            str_contains($fieldName, 'description') || str_contains($fieldName, 'note') || str_contains($fieldName, 'comment') => 'fake()->paragraph()',
            str_contains($fieldName, 'title') => 'fake()->sentence(4)',
            str_contains($fieldName, 'url') || str_contains($fieldName, 'website') || str_contains($fieldName, 'link') => 'fake()->url()',
            $type === 'integer' || $type === 'int' => 'fake()->randomNumber()',
            $type === 'float' || $type === 'decimal' => 'fake()->randomFloat(2, 0, 1000)',
            $type === 'boolean' || $type === 'bool' => 'fake()->boolean()',
            $type === 'text' => 'fake()->paragraph()',
            $type === 'date' => 'fake()->date()',
            $type === 'timestamp' || $type === 'datetime' => "fake()->dateTime()->format('Y-m-d H:i:s')",
            $type === 'json' || $type === 'array' => '[]',
            default => 'fake()->words(2, true)',
        };
    }

    // ── Directory creation ─────────────────────────────────────────────────────

    private function createDirectories(string $basePath, string $unitTestPath, string $featureTestPath): void
    {
        $dirs = [
            "{$basePath}/Application/Commands/Create",
            "{$basePath}/Application/Commands/Update",
            "{$basePath}/Application/Commands/Delete",
            "{$basePath}/Application/Data",
            "{$basePath}/Application/Queries/ListAll",
            "{$basePath}/Application/Queries/FindById",
            "{$basePath}/Domain/Database/Factories",
            "{$basePath}/Domain/Events",
            "{$basePath}/Domain/Exceptions",
            "{$basePath}/Domain/Models",
            "{$basePath}/Infrastructure/Repositories",
            "{$basePath}/Presentation/Http/Controllers",
            "{$basePath}/Presentation/Http/OpenApi",
            "{$basePath}/Presentation/Http/Requests",
            "{$basePath}/Providers",
            $unitTestPath,
            $featureTestPath,
        ];

        if ($this->option('with-cache-warmer')) {
            $dirs[] = "{$basePath}/Infrastructure/Cache";
        }

        foreach ($dirs as $dir) {
            $this->files->ensureDirectoryExists($dir);
        }
    }

    // ── File generation ────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function generateFiles(
        string $name,
        string $table,
        string $plural,
        array $fields,
        string $basePath,
        string $unitTestPath,
        string $featureTestPath,
    ): void {
        $map = [
            "{$basePath}/Domain/Models/{$name}.php" => $this->stubModel($name, $table, $fields),
            "{$basePath}/Domain/Database/Factories/{$name}Factory.php" => $this->stubFactory($name, $fields),
            "{$basePath}/Application/Data/Create{$name}Data.php" => $this->stubCreateData($name, $fields),
            "{$basePath}/Application/Data/Update{$name}Data.php" => $this->stubUpdateData($name, $fields),
            "{$basePath}/Application/Data/{$name}Resource.php" => $this->stubResource($name, $fields),
            "{$basePath}/Application/Data/{$name}FilterData.php" => $this->stubFilterData($name, $fields),
            "{$basePath}/Domain/Exceptions/{$name}NotFoundException.php" => $this->stubNotFoundException($name),
            "{$basePath}/Infrastructure/Repositories/{$name}Repository.php" => $this->stubRepository($name),
            "{$basePath}/Application/Commands/Create/Create{$name}Command.php" => $this->stubCreateCommand($name, $fields),
            "{$basePath}/Application/Commands/Create/Create{$name}Handler.php" => $this->stubCreateHandler($name, $fields),
            "{$basePath}/Application/Commands/Update/Update{$name}Command.php" => $this->stubUpdateCommand($name, $fields),
            "{$basePath}/Application/Commands/Update/Update{$name}Handler.php" => $this->stubUpdateHandler($name, $fields),
            "{$basePath}/Application/Commands/Delete/Delete{$name}Command.php" => $this->stubDeleteCommand($name),
            "{$basePath}/Application/Commands/Delete/Delete{$name}Handler.php" => $this->stubDeleteHandler($name),
            "{$basePath}/Application/Queries/ListAll/List{$name}sQuery.php" => $this->stubListQuery($name),
            "{$basePath}/Application/Queries/ListAll/List{$name}sHandler.php" => $this->stubListHandler($name),
            "{$basePath}/Application/Queries/FindById/Find{$name}ByIdQuery.php" => $this->stubFindByIdQuery($name),
            "{$basePath}/Application/Queries/FindById/Find{$name}ByIdHandler.php" => $this->stubFindByIdHandler($name),
            "{$basePath}/Domain/Events/{$name}Created.php" => $this->stubEvent($name, 'Created'),
            "{$basePath}/Domain/Events/{$name}Updated.php" => $this->stubEvent($name, 'Updated'),
            "{$basePath}/Domain/Events/{$name}Deleted.php" => $this->stubEvent($name, 'Deleted'),
            "{$basePath}/Presentation/Http/Requests/List{$name}sRequest.php" => $this->stubListRequest($name),
            "{$basePath}/Presentation/Http/Requests/Store{$name}Request.php" => $this->stubStoreRequest($name),
            "{$basePath}/Presentation/Http/Requests/Update{$name}Request.php" => $this->stubUpdateRequest($name),
            "{$basePath}/Presentation/Http/Controllers/{$name}Controller.php" => $this->stubController($name, $fields),
            "{$basePath}/Presentation/Http/OpenApi/{$name}OpenApi.php" => $this->stubOpenApi($name, $plural, $fields),
            "{$basePath}/Providers/{$name}ServiceProvider.php" => $this->stubServiceProvider($name),
            "{$unitTestPath}/{$name}RepositoryTest.php" => $this->stubRepositoryTest($name, $fields),
            "{$featureTestPath}/{$name}ApiTest.php" => $this->stubApiTest($name, $plural, $fields),
        ];

        if ($this->option('with-cache-warmer')) {
            $map["{$basePath}/Infrastructure/Cache/{$name}CacheWarmer.php"] = $this->stubCacheWarmer($name);
        }

        foreach ($map as $path => $content) {
            $this->writeFile($path, $content);
        }

        $this->appendRoutes($name, $plural);
    }

    private function writeFile(string $path, string $content): void
    {
        if ($this->files->exists($path)) {
            $this->warn("  Skipped (exists): {$path}");

            return;
        }

        $this->files->put($path, $content);
        $this->line("  <info>Created:</info> {$path}");
    }

    // ── Migration ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function generateMigration(string $name, string $table, array $fields): void
    {
        $existing = $this->files->glob(database_path("migrations/*_create_{$table}_table.php"));
        if (! empty($existing)) {
            $this->warn("  Skipped (exists): migration for table {$table}");

            return;
        }

        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");

        $columns = '';
        foreach ($fields as $fieldName => $def) {
            if ($def['migration'] === 'decimal') {
                $columns .= "            \$table->decimal('{$fieldName}', 10, 2);\n";
            } elseif ($def['migration'] === 'string') {
                $columns .= "            \$table->string('{$fieldName}');\n";
            } else {
                $columns .= "            \$table->{$def['migration']}('{$fieldName}');\n";
            }
        }

        $content = <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table): void {
                    \$table->id();
        {$columns}            \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP;

        $this->files->put($path, $content);
        $this->line("  <info>Created:</info> {$path}");

        $this->call('migrate');
    }

    // ── Provider registration ──────────────────────────────────────────────────

    private function registerProvider(string $name): void
    {
        $providerClass = "{$this->ns}\\Providers\\{$name}ServiceProvider";
        $shortName = "{$name}ServiceProvider";
        $file = base_path('bootstrap/providers.php');
        $content = $this->files->get($file);

        // Ищем в секции массива между 'return [' и '];' строку, содержащую $shortName . '::class'
        $pattern = '/return\s*\[\s*(.*?)\s*\]\s*;/s';
        if (preg_match($pattern, $content, $matches)) {
            $arrayContent = $matches[1];
            if (str_contains($arrayContent, $shortName.'::class')) {
                $this->warn("  Skipped (exists): provider {$providerClass}");

                return;
            }
        }

        // Добавляем новую строку перед закрывающей скобкой массива
        $content = str_replace(
            '];',
            "    {$providerClass}::class,\n];",
            $content
        );

        $this->files->put($file, $content);
        $this->line("  <info>Registered:</info> {$providerClass}");
    }

    // ── Stubs ──────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubModel(string $name, string $table, array $fields): string
    {
        $fillable = implode(', ', array_map(fn ($f) => "'{$f}'", array_keys($fields)));

        $esImports = $this->option('with-elasticsearch')
            ? "\nuse Shared\\Elasticsearch\\ElasticsearchSearchable;\nuse Shared\\Elasticsearch\\InteractsWithElasticsearch;"
            : '';
        $esInterface = $this->option('with-elasticsearch') ? ' implements ElasticsearchSearchable' : '';
        $esTrait = $this->option('with-elasticsearch') ? "\n    use InteractsWithElasticsearch;\n" : '';

        return <<<PHP
        <?php

        namespace {$this->ns}\\Domain\\Models;

        use {$this->ns}\\Domain\\Database\\Factories\\{$name}Factory;
        use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
        use Illuminate\\Database\\Eloquent\\Model;{$esImports}

        class {$name} extends Model{$esInterface}
        {{$esTrait}

            /** @use HasFactory<{$name}Factory> */
            use HasFactory;

            protected \$table = '{$table}';

            /** @var list<string> */
            protected \$fillable = [{$fillable}];

            protected static function newFactory(): {$name}Factory
            {
                return {$name}Factory::new();
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubFactory(string $name, array $fields): string
    {
        $definition = '';
        foreach ($fields as $fieldName => $def) {
            $definition .= "            '{$fieldName}' => {$def['faker']},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Domain\\Database\\Factories;

        use {$this->ns}\\Domain\\Models\\{$name};
        use Illuminate\\Database\\Eloquent\\Factories\\Factory;

        /**
         * @extends Factory<{$name}>
         */
        class {$name}Factory extends Factory
        {
            protected \$model = {$name}::class;

            /** @return array<string, mixed> */
            public function definition(): array
            {
                return [
        {$definition}        ];
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubCreateData(string $name, array $fields): string
    {
        $required = '';
        $optional = '';
        $rules = '';

        foreach ($fields as $fieldName => $def) {
            if ($def['nullable']) {
                $optional .= "        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
            } else {
                $required .= "        public readonly {$def['phpType']} \${$fieldName},\n";
            }

            $ruleList = implode("', '", $def['rules']);
            $rules .= "            '{$fieldName}' => ['{$ruleList}'],\n";
        }

        $props = $required.$optional;

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Data;

        use Shared\\Http\\Data\\BaseData;

        class Create{$name}Data extends BaseData
        {
            public function __construct(
        {$props}    ) {}

            /** @return array<string, list<string>> */
            public static function rules(): array
            {
                return [
        {$rules}        ];
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubUpdateData(string $name, array $fields): string
    {
        $required = '';
        $optional = '';
        $rules = '';

        foreach ($fields as $fieldName => $def) {
            if ($def['nullable']) {
                $optional .= "        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
                $ruleList = implode("', '", $def['rules']);
                $rules .= "            '{$fieldName}' => ['sometimes', '{$ruleList}'],\n";
            } else {
                $required .= "        public readonly {$def['phpType']} \${$fieldName},\n";
                $ruleList = implode("', '", $def['rules']);
                $rules .= "            '{$fieldName}' => ['{$ruleList}'],\n";
            }
        }

        $props = $required.$optional;

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Data;

        use Shared\\Http\\Data\\BaseData;

        class Update{$name}Data extends BaseData
        {
            public function __construct(
        {$props}    ) {}

            /** @return array<string, list<string>> */
            public static function rules(): array
            {
                return [
        {$rules}        ];
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubResource(string $name, array $fields): string
    {
        $required = "        public readonly int \$id,\n";
        $optional = '';

        foreach ($fields as $fieldName => $def) {
            if ($def['nullable']) {
                $optional .= "        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
            } else {
                $required .= "        public readonly {$def['phpType']} \${$fieldName},\n";
            }
        }

        $optional .= "        public readonly ?string \$created_at = null,\n";
        $optional .= "        public readonly ?string \$updated_at = null,\n";
        $props = $required.$optional;

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Data;

        use Shared\\Http\\Data\\BaseData;

        class {$name}Resource extends BaseData
        {
            public function __construct(
        {$props}    ) {}
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubFilterData(string $name, array $fields): string
    {
        $props = '';
        foreach ($fields as $fieldName => $def) {
            $phpType = "?{$def['phpType']}";
            $props .= "        public readonly {$phpType} \${$fieldName} = null,\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Data;

        use Spatie\\LaravelData\\Data;

        class {$name}FilterData extends Data
        {
            public function __construct(
        {$props}    ) {}
        }
        PHP;
    }

    private function stubNotFoundException(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Domain\\Exceptions;

        use RuntimeException;

        final class {$name}NotFoundException extends RuntimeException
        {
            public static function forId(int|string \$id): self
            {
                return new self("{$name} not found: {\$id}");
            }
        }
        PHP;
    }

    private function stubRepository(string $name): string
    {
        $esImports = $this->option('with-elasticsearch')
            ? "\nuse Shared\\Elasticsearch\\ElasticsearchSearchable;\nuse Shared\\Elasticsearch\\InteractsWithElasticsearch;"
            : '';
        $esInterface = $this->option('with-elasticsearch') ? ' implements ElasticsearchSearchable' : '';
        $esTrait = $this->option('with-elasticsearch') ? "\n    use InteractsWithElasticsearch;\n" : '';

        return <<<PHP
        <?php

        namespace {$this->ns}\\Infrastructure\\Repositories;

        use Shared\\Repository\\BaseRepository;
        use {$this->ns}\\Domain\\Models\\{$name};{$esImports}

        class {$name}Repository extends BaseRepository{$esInterface}
        {{$esTrait}
            protected string \$model = {$name}::class;
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubCreateCommand(string $name, array $fields): string
    {
        $params = '';
        foreach ($fields as $fieldName => $def) {
            $phpType = $def['nullable'] ? "?{$def['phpType']}" : $def['phpType'];
            $default = $def['nullable'] ? ' = null' : '';
            $params .= "        public {$phpType} \${$fieldName}{$default},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Create;

        use Shared\\Bus\\BaseCommand;

        readonly class Create{$name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubCreateHandler(string $name, array $fields): string
    {
        $arrayItems = '';
        foreach ($fields as $fieldName => $def) {
            $arrayItems .= "                '{$fieldName}' => \$message->{$fieldName},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Create;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$this->ns}\\Domain\\Events\\{$name}Created;
        use {$this->ns}\\Domain\\Models\\{$name};
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;

        readonly class Create{$name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$name}Repository \$repository) {}

            public function handle(object \$message): int
            {
                assert(\$message instanceof Create{$name}Command);

                /** @var {$name} \$record */
                \$record = \$this->repository->create([
        {$arrayItems}        ]);

                {$name}Created::dispatch(\$record);

                return \$record->id;
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubUpdateCommand(string $name, array $fields): string
    {
        $params = "        public int \$id,\n";
        foreach ($fields as $fieldName => $def) {
            $phpType = $def['nullable'] ? "?{$def['phpType']}" : $def['phpType'];
            $default = $def['nullable'] ? ' = null' : '';
            $params .= "        public {$phpType} \${$fieldName}{$default},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Update;

        use Shared\\Bus\\BaseCommand;

        readonly class Update{$name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubUpdateHandler(string $name, array $fields): string
    {
        $arrayItems = '';
        foreach ($fields as $fieldName => $def) {
            $arrayItems .= "                '{$fieldName}' => \$message->{$fieldName},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Update;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$this->ns}\\Domain\\Events\\{$name}Updated;
        use {$this->ns}\\Domain\\Models\\{$name};
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;

        readonly class Update{$name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$name}Repository \$repository) {}

            public function handle(object \$message): null
            {
                assert(\$message instanceof Update{$name}Command);

                /** @var {$name} \$record */
                \$record = \$this->repository->findOrFail(\$message->id);
                \$updated = \$this->repository->update(\$record, [
        {$arrayItems}        ]);

                {$name}Updated::dispatch(\$updated);

                return null;
            }
        }
        PHP;
    }

    private function stubDeleteCommand(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Delete;

        use Shared\\Bus\\BaseCommand;

        readonly class Delete{$name}Command implements BaseCommand
        {
            public function __construct(public int \$id) {}
        }
        PHP;
    }

    private function stubDeleteHandler(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Commands\\Delete;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$this->ns}\\Domain\\Events\\{$name}Deleted;
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;

        readonly class Delete{$name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$name}Repository \$repository) {}

            public function handle(object \$message): null
            {
                assert(\$message instanceof Delete{$name}Command);

                \$record = \$this->repository->findOrFail(\$message->id);
                \$this->repository->delete(\$record);
                {$name}Deleted::dispatch(\$record);

                return null;
            }
        }
        PHP;
    }

    private function stubListQuery(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Queries\\ListAll;

        use Shared\\Bus\\ListEntityQuery;

        class List{$name}sQuery extends ListEntityQuery
        {
            /** @var list<string> */
            public const SORTABLE = ['id'];
        }
        PHP;
    }

    private function stubListHandler(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Queries\\ListAll;

        use Shared\\Bus\\QueryHandlerInterface;
        use {$this->ns}\\Domain\\Models\\{$name};
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;
        use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;

        readonly class List{$name}sHandler implements QueryHandlerInterface
        {
            public function __construct(private readonly {$name}Repository \$repository) {}

            /** @return LengthAwarePaginator<int, {$name}> */
            public function handle(object \$message): LengthAwarePaginator
            {
                assert(\$message instanceof List{$name}sQuery);

                /** @var LengthAwarePaginator<int, {$name}> \$result */
                \$result = \$this->repository->list(
                    \$message->filters,
                    \$message->sort,
                    \$message->pagination,
                );

                return \$result;
            }
        }
        PHP;
    }

    private function stubFindByIdQuery(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Queries\\FindById;

        use Shared\\Bus\\BaseQuery;

        readonly class Find{$name}ByIdQuery implements BaseQuery
        {
            public function __construct(public readonly int \$id) {}
        }
        PHP;
    }

    private function stubFindByIdHandler(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Application\\Queries\\FindById;

        use Shared\\Bus\\QueryHandlerInterface;
        use {$this->ns}\\Domain\\Models\\{$name};
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;

        readonly class Find{$name}ByIdHandler implements QueryHandlerInterface
        {
            public function __construct(private readonly {$name}Repository \$repository) {}

            public function handle(object \$message): {$name}
            {
                assert(\$message instanceof Find{$name}ByIdQuery);

                /** @var {$name} \$record */
                \$record = \$this->repository->findOrFail(\$message->id);

                return \$record;
            }
        }
        PHP;
    }

    private function stubEvent(string $name, string $event): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Domain\\Events;

        use {$this->ns}\\Domain\\Models\\{$name};
        use Illuminate\\Broadcasting\\InteractsWithSockets;
        use Illuminate\\Foundation\\Events\\Dispatchable;
        use Illuminate\\Queue\\SerializesModels;

        class {$name}{$event}
        {
            use Dispatchable;
            use InteractsWithSockets;
            use SerializesModels;

            public function __construct(public readonly {$name} \$record) {}
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubController(string $name, array $fields): string
    {
        $createArgs = '';
        $updateArgs = '';
        foreach ($fields as $fieldName => $def) {
            $createArgs .= "                {$fieldName}: \$dto->{$fieldName},\n";
            $updateArgs .= "                {$fieldName}: \$dto->{$fieldName},\n";
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Presentation\\Http\\Controllers;

        use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;
        use Illuminate\\Http\\JsonResponse;
        use Illuminate\\Http\\Request;
        use Illuminate\\Routing\\Controller;
        use Shared\\Bus\\CommandBusInterface;
        use Shared\\Bus\\QueryBusInterface;
        use Spatie\\LaravelData\\PaginatedDataCollection;
        use {$this->ns}\\Application\\Commands\\Create\\Create{$name}Command;
        use {$this->ns}\\Application\\Commands\\Delete\\Delete{$name}Command;
        use {$this->ns}\\Application\\Commands\\Update\\Update{$name}Command;
        use {$this->ns}\\Application\\Data\\Create{$name}Data;
        use {$this->ns}\\Application\\Data\\Update{$name}Data;
        use {$this->ns}\\Application\\Data\\{$name}Resource;
        use {$this->ns}\\Application\\Queries\\FindById\\Find{$name}ByIdQuery;
        use {$this->ns}\\Application\\Queries\\ListAll\\List{$name}sQuery;
        use {$this->ns}\\Domain\\Models\\{$name};
        use {$this->ns}\\Presentation\\Http\\Requests\\List{$name}sRequest;
        use {$this->ns}\\Presentation\\Http\\Requests\\Store{$name}Request;
        use {$this->ns}\\Presentation\\Http\\Requests\\Update{$name}Request;

        class {$name}Controller extends Controller
        {
            public function __construct(
                private readonly CommandBusInterface \$commands,
                private readonly QueryBusInterface \$queries,
            ) {}

            public function index(List{$name}sRequest \$request): JsonResponse
            {
                /** @var LengthAwarePaginator<int, {$name}> \$paginator */
                \$paginator = \$this->queries->ask(new List{$name}sQuery(
                    filters: \$request->toFilters(),
                    sort: \$request->toSort(),
                    pagination: \$request->toPagination(),
                ));

                return response()->json({$name}Resource::collect(\$paginator, PaginatedDataCollection::class));
            }

            public function show(int \$id): JsonResponse
            {
                /** @var {$name} \$record */
                \$record = \$this->queries->ask(new Find{$name}ByIdQuery(\$id));

                return response()->json({$name}Resource::from(\$record));
            }

            public function store(Store{$name}Request \$request): JsonResponse
            {
                \$dto = Create{$name}Data::from(\$request);

                \$id = \$this->commands->dispatch(new Create{$name}Command(
        {$createArgs}        ));
                assert(\$id !== null)

                /** @var {$name} \$record */
                \$record = \$this->queries->ask(new Find{$name}ByIdQuery(\$id));

                return response()->json({$name}Resource::from(\$record), 201);
            }

            public function update(int \$id, Update{$name}Request \$request): JsonResponse
            {
                \$dto = Update{$name}Data::from(\$request);

                \$this->commands->dispatch(new Update{$name}Command(
                    id: \$id,
        {$updateArgs}        ));

                /** @var {$name} \$record */
                \$record = \$this->queries->ask(new Find{$name}ByIdQuery(\$id));

                return response()->json({$name}Resource::from(\$record));
            }

            public function destroy(int \$id, Request \$request): JsonResponse
            {
                \$this->commands->dispatch(new Delete{$name}Command(\$id));

                return response()->json(null, 204);
            }
        }
        PHP;
    }

    private function stubListRequest(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Presentation\\Http\\Requests;

        use {$this->ns}\\Application\\Data\\{$name}FilterData;
        use {$this->ns}\\Application\\Queries\\ListAll\\List{$name}sQuery;
        use Shared\\Http\\Requests\\ListRequest;

        class List{$name}sRequest extends ListRequest
        {
            protected const SORTABLE = List{$name}sQuery::SORTABLE;

            public function toFilters(): {$name}FilterData
            {
                return {$name}FilterData::from(\$this->all());
            }
        }
        PHP;
    }

    private function stubStoreRequest(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Presentation\\Http\\Requests;

        use {$this->ns}\\Application\\Data\\Create{$name}Data;
        use Illuminate\\Foundation\\Http\\FormRequest;

        class Store{$name}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            /** @return array<string, list<mixed>> */
            public function rules(): array
            {
                return Create{$name}Data::rules();
            }
        }
        PHP;
    }

    private function stubUpdateRequest(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Presentation\\Http\\Requests;

        use {$this->ns}\\Application\\Data\\Update{$name}Data;
        use Illuminate\\Foundation\\Http\\FormRequest;

        class Update{$name}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            /** @return array<string, list<mixed>> */
            public function rules(): array
            {
                return Update{$name}Data::rules();
            }
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubOpenApi(string $name, string $plural, array $fields): string
    {
        $schemaProps = "        new OA\\Property(property: 'id', type: 'integer'),\n";
        foreach ($fields as $fieldName => $def) {
            $oaType = match ($def['phpType']) {
                'int' => 'integer',
                'float' => 'number',
                'bool' => 'boolean',
                'array' => 'array',
                default => 'string',
            };
            $schemaProps .= "        new OA\\Property(property: '{$fieldName}', type: '{$oaType}'),\n";
        }
        $schemaProps .= "        new OA\\Property(property: 'created_at', type: 'string', format: 'date-time'),\n";
        $schemaProps .= "        new OA\\Property(property: 'updated_at', type: 'string', format: 'date-time'),\n";

        return "<?php\n\nnamespace {$this->ns}\\Presentation\\Http\\OpenApi;\n\nuse OpenApi\\Attributes as OA;\n\n"
            ."#[OA\\Tag(name: '{$name}s', description: '{$name} management')]\n"
            ."#[OA\\Schema(\n"
            ."    schema: '{$name}',\n"
            ."    properties: [\n"
            ."{$schemaProps}"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '/api/v1/{$plural}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'List {$name}s',\n"
            ."    responses: [new OA\\Response(response: 200, description: 'Paginated list')],\n"
            .")]\n"
            ."#[OA\\Post(\n"
            ."    path: '/api/v1/{$plural}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Create {$name}',\n"
            ."    responses: [new OA\\Response(response: 201, description: 'Created')],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Get {$name} by ID',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Success'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Put(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Update {$name}',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Updated'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Delete(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Delete {$name}',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [new OA\\Response(response: 204, description: 'Deleted')],\n"
            .")]\n"
            ."class {$name}OpenApi {}\n";
    }

    private function appendRoutes(string $name, string $plural): void
    {
        $file = base_path('routes/api.php');
        $content = $this->files->get($file);
        $controllerFqcn = "{$this->ns}\\Presentation\\Http\\Controllers\\{$name}Controller";

        if (str_contains($content, $controllerFqcn)) {
            $this->warn("  Skipped (exists): routes for {$name} in routes/api.php");

            return;
        }

        $routeName = str_replace('/', '.', $plural);

        $block = <<<PHP


        Route::prefix('v1/{$plural}')->group(function (): void {
            Route::get('/', [\\{$controllerFqcn}::class, 'index'])->name('api.v1.{$routeName}.index');
            Route::post('/', [\\{$controllerFqcn}::class, 'store'])->name('api.v1.{$routeName}.store');
            Route::get('/{id}', [\\{$controllerFqcn}::class, 'show'])->name('api.v1.{$routeName}.show');
            Route::put('/{id}', [\\{$controllerFqcn}::class, 'update'])->name('api.v1.{$routeName}.update');
            Route::delete('/{id}', [\\{$controllerFqcn}::class, 'destroy'])->name('api.v1.{$routeName}.destroy');
        });
        PHP;

        $this->files->append($file, $block);
        $this->line("  <info>Appended:</info> routes for {$name} to routes/api.php");
    }

    private function stubServiceProvider(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Providers;

        use {$this->ns}\\Application\\Commands\\Create\\Create{$name}Handler;
        use {$this->ns}\\Application\\Commands\\Delete\\Delete{$name}Handler;
        use {$this->ns}\\Application\\Commands\\Update\\Update{$name}Handler;
        use {$this->ns}\\Application\\Queries\\FindById\\Find{$name}ByIdHandler;
        use {$this->ns}\\Application\\Queries\\ListAll\\List{$name}sHandler;
        use Illuminate\\Support\\ServiceProvider;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                \$this->app->bind(Create{$name}Handler::class);
                \$this->app->bind(Update{$name}Handler::class);
                \$this->app->bind(Delete{$name}Handler::class);
                \$this->app->bind(List{$name}sHandler::class);
                \$this->app->bind(Find{$name}ByIdHandler::class);
            }

            public function boot(): void {}
        }
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubRepositoryTest(string $name, array $fields): string
    {
        $makeData = '';
        $updateData = '';
        $filterTests = '';

        foreach ($fields as $fieldName => $def) {
            $val = $this->testValueFor($fieldName, $def['phpType']);
            $updateVal = $this->testValueFor($fieldName, $def['phpType'], true);
            $makeData .= "            '{$fieldName}' => {$val},\n";
            $updateData .= "            '{$fieldName}' => {$updateVal},\n";

            if (in_array($def['phpType'], ['string', 'int'], true)) {
                $filterTests .= $this->filterTestMethod($name, $fieldName, $def['phpType']);
            }
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Tests\\Unit;

        use Shared\\Repository\\BaseRepository;
        use Shared\\Testing\\BaseRepositoryTest;
        use {$this->ns}\\Infrastructure\\Repositories\\{$name}Repository;

        class {$name}RepositoryTest extends BaseRepositoryTest
        {
            protected function repository(): BaseRepository
            {
                return new {$name}Repository;
            }

            /** @return array<string, mixed> */
            protected function makeModelData(): array
            {
                return [
        {$makeData}        ];
            }

            /** @return array<string, mixed> */
            protected function updateModelData(): array
            {
                return [
        {$updateData}        ];
            }
        {$filterTests}}
        PHP;
    }

    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    private function stubApiTest(string $name, string $plural, array $fields): string
    {
        $storePayload = '';
        $updatePayload = '';
        $filterTests = '';
        $firstStringField = '';
        $firstStringValue = '';
        $firstStringValue2 = '';

        foreach ($fields as $fieldName => $def) {
            $val = $this->testValueFor($fieldName, $def['phpType']);
            $updateVal = $this->testValueFor($fieldName, $def['phpType'], true);
            $storePayload .= "            '{$fieldName}' => {$val},\n";
            $updatePayload .= "            '{$fieldName}' => {$updateVal},\n";

            if ($firstStringField === '' && $def['phpType'] === 'string') {
                $firstStringField = $fieldName;
                $firstStringValue = trim($val, "'");
                $firstStringValue2 = trim($updateVal, "'");
            }
        }

        if ($firstStringField !== '') {
            $filterTests = $this->apiFilterTestMethod($name, $plural, $firstStringField, $firstStringValue, $firstStringValue2);
        }

        return <<<PHP
        <?php

        namespace {$this->ns}\\Tests\\Feature;

        use Shared\\Testing\\BaseApiTest;
        use {$this->ns}\\Domain\\Models\\{$name};
        use Illuminate\\Database\\Eloquent\\Model;

        class {$name}ApiTest extends BaseApiTest
        {
            protected function basePath(): string
            {
                return '/api/v1/{$plural}';
            }

            /** @return array<string, mixed> */
            protected function makeStorePayload(): array
            {
                return [
        {$storePayload}        ];
            }

            /** @return array<string, mixed> */
            protected function makeUpdatePayload(): array
            {
                return [
        {$updatePayload}        ];
            }

            protected function existingRecord(): Model
            {
                return {$name}::factory()->create();
            }

            public function test_index_paginates(): void
            {
                {$name}::factory()->count(5)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?per_page=2&page=1");

                \$response->assertStatus(200);
                \$this->assertCount(2, \$response->json('data'));
                \$this->assertEquals(1, \$response->json('meta.current_page'));
            }

            public function test_index_sorts_asc(): void
            {
                {$name}::factory()->count(3)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?sort=id&direction=asc");

                \$response->assertStatus(200);
                \$data = \$response->json('data');
                \$this->assertNotEmpty(\$data);
                \$this->assertLessThanOrEqual(\$data[1]['id'] ?? PHP_INT_MAX, \$data[0]['id']);
            }

            public function test_index_sorts_desc(): void
            {
                {$name}::factory()->count(3)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?sort=id&direction=desc");

                \$response->assertStatus(200);
                \$data = \$response->json('data');
                \$this->assertNotEmpty(\$data);
                \$this->assertGreaterThanOrEqual(\$data[1]['id'] ?? 0, \$data[0]['id']);
            }
        {$filterTests}}
        PHP;
    }

    private function filterTestMethod(string $name, string $fieldName, string $phpType): string
    {
        $val1 = $this->testValueFor($fieldName, $phpType);
        $val2 = $this->testValueFor($fieldName, $phpType, true);
        $methodName = 'test_list_filters_by_'.$fieldName;

        return <<<PHP

            public function {$methodName}(): void
            {
                \$this->repository()->create(array_merge(\$this->makeModelData(), ['{$fieldName}' => {$val1}]));
                \$this->repository()->create(array_merge(\$this->makeModelData(), ['{$fieldName}' => {$val2}]));

                \$filters = new \\{$this->ns}\\Application\\Data\\{$name}FilterData({$fieldName}: {$val1});
                \$result = \$this->repository()->list(\$filters);

                \$this->assertSame(1, \$result->total());
            }

        PHP;
    }

    private function apiFilterTestMethod(string $name, string $plural, string $field, string $val1, string $val2): string
    {
        return <<<PHP

            public function test_index_filters_by_{$field}(): void
            {
                {$name}::factory()->create(['{$field}' => '{$val1}']);
                {$name}::factory()->create(['{$field}' => '{$val2}']);

                \$response = \$this->getJson("/api/v1/{$plural}?{$field}=" . urlencode('{$val1}'));

                \$response->assertStatus(200);
                \$this->assertCount(1, \$response->json('data'));
            }

        PHP;
    }

    private function testValueFor(string $fieldName, string $phpType, bool $alternate = false): string
    {
        if ($phpType === 'int') {
            return $alternate ? '99' : '42';
        }

        if ($phpType === 'float') {
            return $alternate ? '99.99' : '42.50';
        }

        if ($phpType === 'bool') {
            return $alternate ? 'false' : 'true';
        }

        if (str_ends_with($fieldName, 'email')) {
            return $alternate ? "'updated@example.com'" : "'test@example.com'";
        }

        if (str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel')) {
            return $alternate ? "'+7 800 000 0001'" : "'+7 800 000 0000'";
        }

        if (str_contains($fieldName, 'first_name')) {
            return $alternate ? "'Jane'" : "'John'";
        }

        if (str_contains($fieldName, 'last_name')) {
            return $alternate ? "'Smith'" : "'Doe'";
        }

        $label = Str::headline($fieldName);

        return $alternate ? "'Updated {$label}'" : "'Test {$label}'";
    }

    private function stubCacheWarmer(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->ns}\\Infrastructure\\Cache;

        use Shared\\Cache\\CacheWarmerInterface;

        class {$name}CacheWarmer implements CacheWarmerInterface
        {
            public function warm(): void {}

            public function priority(): int
            {
                return 10;
            }
        }
        PHP;
    }
}
