<?php

namespace Shared\Console\DomainGenerator\Generators\Database;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Context\DomainContext;

class MigrationGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $existing = $files->glob(database_path("migrations/*_create_{$ctx->table}_table.php"));
        if (! empty($existing)) {
            return;
        }

        $columns = '';
        foreach ($ctx->fields as $fieldName => $def) {
            if ($def['migration'] === 'decimal') {
                $columns .= "            \$table->decimal('{$fieldName}', 10, 2);\n";
            } elseif ($def['migration'] === 'string') {
                $columns .= "            \$table->string('{$fieldName}');\n";
            } else {
                $columns .= "            \$table->{$def['migration']}('{$fieldName}');\n";
            }
        }

        $table = $ctx->table;

        $content = <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

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

        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");
        $files->put($path, $content);
    }
}
