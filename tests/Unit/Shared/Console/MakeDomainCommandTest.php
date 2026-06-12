<?php

namespace Tests\Unit\Shared\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeDomainCommandTest extends StubGenTestCase
{
    private string $originalProviders;

    private string $originalApiRoutes;

    private bool $migrationRan = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalProviders = $this->files->get(base_path('bootstrap/providers.php'));
        $this->originalApiRoutes = $this->files->get(base_path('routes/api.php'));
        $this->cleanupArtifacts();
    }

    protected function tearDown(): void
    {
        if (isset($this->files)) {
            $this->cleanupArtifacts();
            $this->files->put(base_path('bootstrap/providers.php'), $this->originalProviders);
            $this->files->put(base_path('routes/api.php'), $this->originalApiRoutes);
        }

        parent::tearDown();
    }

    private function cleanupArtifacts(): void
    {
        static::deleteDomainArtifacts($this->files);

        if ($this->migrationRan) {
            Schema::dropIfExists('stub_gens');
            DB::table('migrations')->where('migration', 'like', '%create_stub_gens_table')->delete();
            $this->migrationRan = false;
        }
    }

    // ── ISOLATED TESTS ────────────────────────────────────────────────────────
    // Each test generates its own domain variant with its own setUp/tearDown cycle.

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
        $files = $this->files;

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
