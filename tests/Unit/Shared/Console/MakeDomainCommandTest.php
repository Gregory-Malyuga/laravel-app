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

    /** Set to true only in test_creates_and_runs_migration so tearDown skips DB work otherwise. */
    private bool $migrationRan = false;

    /** bootstrap/providers.php content before the shared domain was first generated. */
    private static string $trueOriginalProviders = '';

    /** routes/api.php content before the shared domain was first generated. */
    private static string $trueOriginalApiRoutes = '';

    /** True once the shared StubGen domain has been generated; persists across tests in this class. */
    private static bool $sharedDomainReady = false;

    /**
     * Tests that only read a standard (no flags, no fields) StubGen domain.
     * These share a single make:domain run so only ONE artisan call is needed for all 17.
     * They MUST be defined before the isolated tests to ensure correct ordering.
     */
    private const array SHARED_TESTS = [
        'test_creates_standard_domain_structure',
        'test_no_cache_warmer_without_flag',
        'test_repository_without_elasticsearch_by_default',
        'test_generated_repository_has_correct_namespace',
        'test_creates_commands_queries_events_directories',
        'test_creates_cqrs_files',
        'test_creates_model_in_domain',
        'test_creates_factory_in_domain',
        'test_creates_controller',
        'test_creates_form_requests',
        'test_list_handler_uses_typed_paginator',
        'test_list_query_extends_list_entity_query',
        'test_appends_routes_to_global_api_file',
        'test_creates_api_test',
        'test_creates_events',
        'test_registers_provider_in_bootstrap',
        'test_service_provider_has_handler_bindings',
    ];

    private function isSharedTest(): bool
    {
        return in_array($this->name(), self::SHARED_TESTS, true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->domainBase = base_path('app/Domains/StubGen');
        $this->unitTestBase = base_path('app/Domains/StubGen/Tests/Unit');
        $this->featureTestBase = base_path('app/Domains/StubGen/Tests/Feature');

        if ($this->isSharedTest()) {
            if (! self::$sharedDomainReady) {
                // Remove any test artifacts left by a previously interrupted run before locking in
                // the true originals — otherwise the "originals" would capture dirty state.
                $this->stripTestArtifacts();
                self::$trueOriginalProviders = $this->files->get(base_path('bootstrap/providers.php'));
                self::$trueOriginalApiRoutes = $this->files->get(base_path('routes/api.php'));
                $this->cleanupStubGenArtifacts();
                $this->artisan('make:domain StubGen')->assertSuccessful();
                self::$sharedDomainReady = true;
            }
            // Instance vars point at true originals so shared tearDown is a no-op.
            $this->originalProviders = self::$trueOriginalProviders;
            $this->originalApiRoutes = self::$trueOriginalApiRoutes;
        } else {
            // Transition: first isolated test after shared phase restores clean state eagerly so
            // that each isolated test's tearDown always restores to truly clean files.
            if (self::$sharedDomainReady) {
                $this->cleanupStubGenArtifacts();
                $this->files->put(base_path('bootstrap/providers.php'), self::$trueOriginalProviders);
                $this->files->put(base_path('routes/api.php'), self::$trueOriginalApiRoutes);
                self::$sharedDomainReady = false;
            }
            $this->originalProviders = $this->files->get(base_path('bootstrap/providers.php'));
            $this->originalApiRoutes = $this->files->get(base_path('routes/api.php'));
            $this->cleanupStubGenArtifacts();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->files)) {
            if (! $this->isSharedTest()) {
                $this->cleanupStubGenArtifacts();
                $this->files->put(base_path('bootstrap/providers.php'), $this->originalProviders);
                $this->files->put(base_path('routes/api.php'), $this->originalApiRoutes);
            }
            // Shared tests leave the domain in place; tearDownAfterClass does the final restore.
        }

        parent::tearDown();
    }

    /**
     * Called once after all tests in the class have run.
     * Restores routes/providers to their true pre-suite state and removes generated artefacts.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$sharedDomainReady) {
            $files = new Filesystem;
            $files->deleteDirectory(base_path('app/Domains/StubGen'));

            foreach ($files->glob(database_path('migrations/*_create_stub_gens_table.php')) as $f) {
                $files->delete($f);
            }

            if (self::$trueOriginalProviders !== '') {
                $files->put(base_path('bootstrap/providers.php'), self::$trueOriginalProviders);
            }

            if (self::$trueOriginalApiRoutes !== '') {
                $files->put(base_path('routes/api.php'), self::$trueOriginalApiRoutes);
            }

            self::$sharedDomainReady = false;
        }

        parent::tearDownAfterClass();
    }

    /**
     * Surgically removes test-generated route blocks and provider entries from shared files.
     * Called once before capturing true originals to guard against stale state from previous
     * interrupted test runs. Matches any domain under Stub* or Bench* namespaces.
     */
    private function stripTestArtifacts(): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        $content = $this->files->get($providersPath);
        $cleaned = preg_replace('/^[ \t]+Domains\\\\(?:Stub|Bench)[^:]+::class,\n/m', '', $content);
        if ($cleaned !== null && $cleaned !== $content) {
            $this->files->put($providersPath, $cleaned);
        }

        $routesPath = base_path('routes/api.php');
        $content = $this->files->get($routesPath);
        // Matches the full route block appended by appendRoutes() for any stub-* or bench-* prefix.
        $cleaned = preg_replace('/\n\nRoute::prefix\(\'v1\/(?:stub|bench)[^\']*\'\)->group.*?\}\);/s', '', $content);
        if ($cleaned !== null && $cleaned !== $content) {
            $this->files->put($routesPath, $cleaned);
        }
    }

    private function cleanupStubGenArtifacts(): void
    {
        $this->files->deleteDirectory($this->domainBase);

        foreach ($this->files->glob(database_path('migrations/*_create_stub_gens_table.php')) as $file) {
            $this->files->delete($file);
        }

        if ($this->migrationRan) {
            Schema::dropIfExists('stub_gens');
            DB::table('migrations')->where('migration', 'like', '%create_stub_gens_table')->delete();
            $this->migrationRan = false;
        }
    }

    // ── SHARED TESTS ──────────────────────────────────────────────────────────
    // Share a single pre-generated StubGen domain. No artisan call in the body.
    // These must stay above the isolated tests so PHPUnit runs them first.

    public function test_creates_standard_domain_structure(): void
    {
        $this->assertFileExists("{$this->domainBase}/Application/Data/CreateStubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/UpdateStubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenResource.php");
        $this->assertFileExists("{$this->domainBase}/Application/Data/StubGenFilterData.php");
        $this->assertFileDoesNotExist("{$this->domainBase}/Application/Data/StubGenData.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Exceptions/StubGenNotFoundException.php");
        $this->assertFileExists("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");
        $this->assertFileExists("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertFileExists("{$this->unitTestBase}/StubGenRepositoryTest.php");
    }

    public function test_no_cache_warmer_without_flag(): void
    {
        $this->assertFileDoesNotExist("{$this->domainBase}/Infrastructure/Cache/StubGenCacheWarmer.php");
    }

    public function test_repository_without_elasticsearch_by_default(): void
    {
        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringNotContainsString('ElasticsearchSearchable', $content);
    }

    public function test_generated_repository_has_correct_namespace(): void
    {
        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringContainsString('namespace Domains\StubGen\Infrastructure\Repositories;', $content);
        $this->assertStringContainsString('class StubGenRepository extends BaseRepository', $content);
    }

    public function test_creates_commands_queries_events_directories(): void
    {
        $this->assertDirectoryExists("{$this->domainBase}/Application/Commands");
        $this->assertDirectoryExists("{$this->domainBase}/Application/Queries");
        $this->assertDirectoryExists("{$this->domainBase}/Domain/Events");
    }

    public function test_creates_cqrs_files(): void
    {
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
        $this->assertFileExists("{$this->domainBase}/Domain/Models/StubGen.php");
        $content = $this->files->get("{$this->domainBase}/Domain/Models/StubGen.php");
        $this->assertStringContainsString('namespace Domains\StubGen\Domain\Models;', $content);
    }

    public function test_creates_factory_in_domain(): void
    {
        $this->assertFileExists("{$this->domainBase}/Domain/Database/Factories/StubGenFactory.php");
    }

    public function test_creates_controller(): void
    {
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $content = $this->files->get("{$this->domainBase}/Presentation/Http/Controllers/StubGenController.php");
        $this->assertStringContainsString('CommandBusInterface', $content);
        $this->assertStringContainsString('QueryBusInterface', $content);
        $this->assertStringContainsString('ListStubGensRequest', $content);
        $this->assertStringContainsString('StoreStubGenRequest', $content);
        $this->assertStringContainsString('UpdateStubGenRequest', $content);
    }

    public function test_creates_form_requests(): void
    {
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/ListStubGensRequest.php");
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/StoreStubGenRequest.php");
        $this->assertFileExists("{$this->domainBase}/Presentation/Http/Requests/UpdateStubGenRequest.php");

        $listContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/ListStubGensRequest.php");
        $this->assertStringContainsString('extends ListRequest', $listContent);
        $this->assertStringContainsString('ListStubGensQuery::SORTABLE', $listContent);

        $storeContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/StoreStubGenRequest.php");
        $this->assertStringContainsString('CreateStubGenData::rules()', $storeContent);

        $updateContent = $this->files->get("{$this->domainBase}/Presentation/Http/Requests/UpdateStubGenRequest.php");
        $this->assertStringContainsString('UpdateStubGenData::rules()', $updateContent);
    }

    public function test_list_handler_uses_typed_paginator(): void
    {
        $content = $this->files->get("{$this->domainBase}/Application/Queries/ListAll/ListStubGensHandler.php");
        $this->assertStringContainsString('LengthAwarePaginator<int, StubGen>', $content);
        $this->assertStringNotContainsString('mixed', $content);
    }

    public function test_list_query_extends_list_entity_query(): void
    {
        $content = $this->files->get("{$this->domainBase}/Application/Queries/ListAll/ListStubGensQuery.php");
        $this->assertStringContainsString('extends ListEntityQuery', $content);
        $this->assertStringContainsString('SORTABLE', $content);
    }

    public function test_appends_routes_to_global_api_file(): void
    {
        $content = $this->files->get(base_path('routes/api.php'));
        $this->assertStringContainsString('StubGenController', $content);
    }

    public function test_creates_api_test(): void
    {
        $this->assertFileExists("{$this->featureTestBase}/StubGenApiTest.php");
        $content = $this->files->get("{$this->featureTestBase}/StubGenApiTest.php");
        $this->assertStringContainsString('BaseApiTest', $content);
    }

    public function test_creates_events(): void
    {
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenCreated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenUpdated.php");
        $this->assertFileExists("{$this->domainBase}/Domain/Events/StubGenDeleted.php");
    }

    public function test_registers_provider_in_bootstrap(): void
    {
        $content = $this->files->get(base_path('bootstrap/providers.php'));
        $this->assertStringContainsString('StubGenServiceProvider', $content);
    }

    public function test_service_provider_has_handler_bindings(): void
    {
        $content = $this->files->get("{$this->domainBase}/Providers/StubGenServiceProvider.php");
        $this->assertStringContainsString('CreateStubGenHandler::class', $content);
        $this->assertStringContainsString('UpdateStubGenHandler::class', $content);
        $this->assertStringContainsString('DeleteStubGenHandler::class', $content);
        $this->assertStringContainsString('ListStubGensHandler::class', $content);
        $this->assertStringContainsString('FindStubGenByIdHandler::class', $content);
    }

    // ── ISOLATED TESTS ────────────────────────────────────────────────────────
    // Each test generates its own domain variant. Run after all shared tests.

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

    public function test_repository_includes_elasticsearch_with_flag(): void
    {
        $this->artisan('make:domain StubGen --with-elasticsearch')->assertSuccessful();

        $content = $this->files->get("{$this->domainBase}/Infrastructure/Repositories/StubGenRepository.php");

        $this->assertStringContainsString('ElasticsearchSearchable', $content);
        $this->assertStringContainsString('InteractsWithElasticsearch', $content);
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

        $this->artisan('migrate')->assertSuccessful();
        $this->migrationRan = true;
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

        // Snapshot both files immediately before generation so the finally block restores
        // exactly what this test found — not whatever setUp captured earlier.
        $routesBefore = $files->get(base_path('routes/api.php'));
        $providersBefore = $files->get(base_path('bootstrap/providers.php'));

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
            $files->put(base_path('routes/api.php'), $routesBefore);
            $files->put(base_path('bootstrap/providers.php'), $providersBefore);
        }
    }
}
