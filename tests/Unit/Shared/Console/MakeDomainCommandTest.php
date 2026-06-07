<?php

namespace Tests\Unit\Shared\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MakeDomainCommandTest extends TestCase
{
    private Filesystem $files;

    private string $domainBase;

    private string $unitTestBase;

    private string $featureTestBase;

    private string $originalProviders;

    private string $originalApiRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->domainBase = base_path('app/Domains/StubGen');
        $this->unitTestBase = base_path('app/Domains/StubGen/Tests/Unit');
        $this->featureTestBase = base_path('app/Domains/StubGen/Tests/Feature');
        $this->originalProviders = $this->files->get(base_path('bootstrap/providers.php'));
        $this->originalApiRoutes = $this->files->get(base_path('routes/api.php'));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->domainBase);

        foreach ($this->files->glob(database_path('migrations/*_create_stub_gens_table.php')) as $file) {
            $this->files->delete($file);
        }

        if (Schema::hasTable('migrations')) {
            DB::table('migrations')->where('migration', 'like', '%create_stub_gens_table')->delete();
        }
        Schema::dropIfExists('stub_gens');

        $this->files->put(base_path('bootstrap/providers.php'), $this->originalProviders);
        $this->files->put(base_path('routes/api.php'), $this->originalApiRoutes);

        parent::tearDown();
    }

    public function test_creates_standard_domain_structure(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenFilterData.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Exceptions/StubGenNotFoundException.php");
        $this->assertFileExists("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");
        $this->assertFileExists("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertFileExists("{$this->unitTestBase}/StubGenRepositoryTest.php");
    }

    public function test_capitalises_first_letter_of_domain_name(): void
    {
        $this->artisan('make:domain stubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");
    }

    public function test_creates_cache_warmer_with_flag(): void
    {
        $this->artisan('make:domain StubGen --with-cache-warmer')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Infrastructure/Cache/StubGenCacheWarmer.php");
    }

    public function test_no_cache_warmer_without_flag(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->domainBase}/Infrastructure/Cache/StubGenCacheWarmer.php");
    }

    public function test_repository_includes_elasticsearch_with_flag(): void
    {
        $this->artisan('make:domain StubGen --with-elasticsearch')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringContainsString('ElasticsearchSearchable', $content);
        $this->assertStringContainsString('InteractsWithElasticsearch', $content);
    }

    public function test_repository_without_elasticsearch_by_default(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringNotContainsString('ElasticsearchSearchable', $content);
    }

    public function test_generated_repository_has_correct_namespace(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringContainsString('namespace Domains\StubGen\Infrastructure\Repositories;', $content);
        $this->assertStringContainsString('class StubGenRepository extends BaseRepository', $content);
    }

    public function test_skips_existing_files_on_second_run(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $path = "{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php";
        $this->files->put($path, '<?php // sentinel');

        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertStringContainsString('sentinel', $this->files->get($path));
    }

    public function test_creates_and_runs_migration(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $migrations = $this->files->glob(database_path('migrations/*_create_stub_gens_table.php'));
        $this->assertNotEmpty($migrations, 'Migration file should be created');
        $this->assertTrue(Schema::hasTable('stub_gens'), 'Table should exist after migrate');
    }

    public function test_skips_migration_if_already_exists(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();
        $before = $this->files->glob(database_path('migrations/*_create_stub_gens_table.php'));

        $this->artisan('make:domain StubGen')->assertSuccessful();
        $after = $this->files->glob(database_path('migrations/*_create_stub_gens_table.php'));

        $this->assertCount(count($before), $after, 'No extra migration should be created on second run');
    }

    public function test_creates_commands_queries_events_directories(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertDirectoryExists("{$this->domainBase}/Application/Commands");
        $this->assertDirectoryExists("{$this->domainBase}/Application/Queries");
        $this->assertDirectoryExists("{$this->domainBase}/Domain/Events");
    }

    public function test_creates_cqrs_files(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Application/Commands/Create/CreateStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Create/CreateStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Update/UpdateStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Update/UpdateStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Delete/DeleteStubGenCommand.php");
        $this->assertFileExists("{$this->domainBase}/Application/Commands/Delete/DeleteStubGenHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/ListAll/ListStubGensQuery.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/ListAll/ListStubGensHandler.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/FindById/FindStubGenByIdQuery.php");
        $this->assertFileExists("{$this->domainBase}/Application/Queries/FindById/FindStubGenByIdHandler.php");
    }

    public function test_creates_model_in_domain(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Domain/Models/StubGen.php");
        $content = $this->files->get("{$this->domainBase}/Domain/Models/StubGen.php");
        $this->assertStringContainsString('namespace Domains\StubGen\Domain\Models;', $content);
    }

    public function test_creates_factory_in_domain(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Domain/Database/Factories/StubGenFactory.php");
    }

    public function test_creates_controller(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $content = $this->files->get("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $this->assertStringContainsString('CommandBusInterface', $content);
        $this->assertStringContainsString('QueryBusInterface', $content);
    }

    public function test_appends_routes_to_global_api_file(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get(base_path('routes/api.php'));
        $this->assertStringContainsString('StubGenController', $content);
    }

    public function test_creates_api_test(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->featureTestBase}/StubGenApiTest.php");
        $content = $this->files->get("{$this->featureTestBase}/StubGenApiTest.php");
        $this->assertStringContainsString('BaseApiTest', $content);
    }

    public function test_creates_events(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenCreated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenUpdated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenDeleted.php");
    }

    public function test_fields_appear_in_model_fillable(): void
    {
        $this->artisan('make:domain StubGen title:string count:integer')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Domain/Models/StubGen.php");
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'count'", $content);
    }

    public function test_fields_appear_in_migration(): void
    {
        $this->artisan('make:domain StubGen title:string count:integer')->assertSuccessful();

        $migrations = $this->files->glob(database_path('migrations/*_create_stub_gens_table.php'));
        $content = $this->files->get($migrations[0]);
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'count'", $content);
    }

    public function test_registers_provider_in_bootstrap(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get(base_path('bootstrap/providers.php'));
        $this->assertStringContainsString('StubGenServiceProvider', $content);
    }

    public function test_service_provider_has_handler_bindings(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertStringContainsString('CreateStubGenHandler::class', $content);
        $this->assertStringContainsString('UpdateStubGenHandler::class', $content);
        $this->assertStringContainsString('DeleteStubGenHandler::class', $content);
        $this->assertStringContainsString('ListStubGensHandler::class', $content);
        $this->assertStringContainsString('FindStubGenByIdHandler::class', $content);
    }

    public function test_provider_registration_is_idempotent(): void
    {
        $this->artisan('make:domain StubGen')->assertSuccessful();
        $this->artisan('make:domain StubGen')->assertSuccessful();

        $content = $this->files->get(base_path('bootstrap/providers.php'));

        $this->assertSame(1, substr_count($content, 'StubGenServiceProvider::class'));
    }

    public function test_nested_domain_uses_parent_namespace(): void
    {
        $nestedBase = base_path('app/Domains/StubGroup/StubGen');
        $files = new Filesystem;

        try {
            $this->artisan('make:domain StubGroup/StubGen')->assertSuccessful();

            $content = $files->get("{$nestedBase}/Infrastructure/Repositories/StubGenRepository.php");
            $this->assertStringContainsString('namespace Domains\StubGroup\StubGen\Infrastructure\Repositories;', $content);

            $content = $files->get("{$nestedBase}/Domain/Models/StubGen.php");
            $this->assertStringContainsString('namespace Domains\StubGroup\StubGen\Domain\Models;', $content);

            $apiRoutes = $files->get(base_path('routes/api.php'));
            $this->assertStringContainsString('Domains\StubGroup\StubGen\Presentation\Http\Controllers\StubGenController', $apiRoutes);
            $this->assertStringContainsString("prefix('v1/stub-group/stub-gens')", $apiRoutes);
        } finally {
            $files->deleteDirectory(base_path('app/Domains/StubGroup'));
            foreach ($files->glob(database_path('migrations/*_create_stub_gens_table.php')) as $f) {
                $files->delete($f);
            }
            Schema::dropIfExists('stub_gens');
            // Restore routes modified by nested domain generation
            $files->put(base_path('routes/api.php'), $this->originalApiRoutes);
        }
    }
}
