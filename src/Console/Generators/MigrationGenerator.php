<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

use Aicl\Console\Support\FieldDefinition;

/**
 * Generates database migration files for entity tables.
 */
class MigrationGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating migration for: {$this->ctx->tableName}";
    }

    public function generate(): array
    {
        $name = $this->ctx->name;
        $tableName = $this->ctx->tableName;
        $timestamp = now()->format('Y_m_d_His');
        $filename = "{$timestamp}_create_{$tableName}_table.php";

        if ($this->ctx->smartMode) {
            $columns = $this->buildSmartMigrationColumns($name, $tableName);
        } else {
            $columns = <<<'COLS'
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
COLS;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
{$columns}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;

        $path = database_path("migrations/{$filename}");
        file_put_contents($path, $content);

        return ["database/migrations/{$filename}"];
    }

    protected function buildSmartMigrationColumns(string $name, string $tableName): string
    {
        $lines = [];
        $lines[] = '            $table->id();';

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->ctx->baseHasColumn('is_active');
        $baseHasOwnerId = $this->ctx->baseHasColumn('owner_id');

        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;

        // Child fields only (base fields are in the base migration)
        foreach ($this->ctx->fields ?? [] as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
            $lines[] = '            '.$this->getMigrationColumnForField($field);
        }

        if (! empty($this->ctx->states)) {
            $defaultState = $this->ctx->states[0];
            $lines[] = "            \$table->string('status')->default('{$defaultState}');";
        }

        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $lines[] = "            \$table->boolean('is_active')->default(true);";
        }
        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $lines[] = "            \$table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();";
        }

        $lines[] = '            $table->timestamps();';
        $lines[] = '            $table->softDeletes();';

        return implode("\n", $lines);
    }

    protected function getMigrationColumnForField(FieldDefinition $field): string
    {
        $col = match ($field->type) {
            'string' => "\$table->string('{$field->name}')",
            'text' => "\$table->text('{$field->name}')",
            'integer' => "\$table->integer('{$field->name}')",
            'float' => "\$table->decimal('{$field->name}', 12, 2)",
            'boolean' => "\$table->boolean('{$field->name}')",
            'date' => "\$table->date('{$field->name}')",
            'datetime' => "\$table->dateTime('{$field->name}')",
            'enum' => "\$table->string('{$field->name}')",
            'json' => "\$table->json('{$field->name}')",
            'foreignId' => "\$table->foreignId('{$field->name}')->constrained('{$field->typeArgument}')->cascadeOnDelete()",
            // @codeCoverageIgnoreStart — Code generation command
            default => "\$table->string('{$field->name}')",
            // @codeCoverageIgnoreEnd
        };

        if ($field->nullable && $field->type !== 'foreignId') {
            $col .= '->nullable()';
        }
        if ($field->unique) {
            $col .= '->unique()';
        }
        if ($field->indexed) {
            $col .= '->index()';
        }
        if ($field->default !== null && $field->type !== 'foreignId') {
            $defaultVal = match (true) {
                $field->default === 'true' => 'true',
                $field->default === 'false' => 'false',
                is_numeric($field->default) => $field->default,
                default => "'{$field->default}'",
            };
            $col .= "->default({$defaultVal})";
        }

        return $col.';';
    }
}
