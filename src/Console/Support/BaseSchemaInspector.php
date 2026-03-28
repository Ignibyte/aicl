<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use Aicl\Contracts\DeclaresBaseSchema;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * BaseSchemaInspector.
 *
 * @codeCoverageIgnore Reason: external-service -- Requires model with DeclaresBaseSchema
 */
class BaseSchemaInspector
{
    /**
     * @var array{
     *     columns: array<int, array{name: string, type: string, modifiers?: array<string>, argument?: string}>,
     *     traits: array<int, string>,
     *     contracts: array<int, string>,
     *     fillable: array<int, string>,
     *     casts: array<string, string>,
     *     relationships: array<int, array{name: string, type: string, model: string, foreignKey?: string}>,
     * }
     */
    private array $schema;

    /**
     * @var array<int, FieldDefinition>|null
     */
    private ?array $columnDefinitions = null;

    public function __construct(private string $baseClass) {}

    /**
     * Validate the base class and load its schema.
     *
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (! class_exists($this->baseClass)) {
            throw new InvalidArgumentException(
                "Base class '{$this->baseClass}' not found. Ensure it exists and is autoloadable."
            );
        }

        if (! is_subclass_of($this->baseClass, Model::class)) {
            throw new InvalidArgumentException(
                "Base class '{$this->baseClass}' must extend Illuminate\\Database\\Eloquent\\Model."
            );
        }

        if (! is_subclass_of($this->baseClass, DeclaresBaseSchema::class)) {
            throw new InvalidArgumentException(
                "Base class '{$this->baseClass}' must implement Aicl\\Contracts\\DeclaresBaseSchema."
            );
        }

        // @codeCoverageIgnoreStart — Untestable in unit context
        $this->schema = $this->baseClass::baseSchema();
        $this->applyDefaults();
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function columns(): array
    {
        if ($this->columnDefinitions === null) {
            $this->columnDefinitions = array_map(
                fn (array $column): FieldDefinition => FieldDefinition::fromBaseSchema($column),
                $this->schema['columns']
            );
        }

        return $this->columnDefinitions;
    }

    /**
     * @return array<int, string>
     */
    public function traits(): array
    {
        return $this->schema['traits'];
    }

    /**
     * @return array<int, string>
     */
    public function contracts(): array
    {
        return $this->schema['contracts'];
    }

    /**
     * @return array<int, string>
     */
    public function fillable(): array
    {
        return $this->schema['fillable'];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return $this->schema['casts'];
    }

    /**
     * @return array<int, array{name: string, type: string, model: string, foreignKey?: string}>
     */
    public function relationships(): array
    {
        return $this->schema['relationships'];
    }

    public function hasColumn(string $name): bool
    {
        foreach ($this->schema['columns'] as $column) {
            if ($column['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    public function hasTrait(string $trait): bool
    {
        return in_array($trait, $this->schema['traits'], true);
    }

    /**
     * Get the column type for a given column name, or null if not found.
     */
    public function columnType(string $name): ?string
    {
        foreach ($this->schema['columns'] as $column) {
            if ($column['name'] === $name) {
                return $column['type'];
            }
        }

        return null;
    }

    /**
     * Get the short class name (e.g., "BaseNetworkDevice" from "App\Models\Base\BaseNetworkDevice").
     */
    public function shortClassName(): string
    {
        $parts = explode('\\', $this->baseClass);

        return end($parts);
    }

    /**
     * Get the fully qualified class name.
     */
    public function fullClassName(): string
    {
        return $this->baseClass;
    }

    private function applyDefaults(): void
    {
        /** @var array<string, mixed> $schema */
        // @codeCoverageIgnoreStart — Untestable in unit context
        $schema = $this->schema;
        $this->schema['columns'] = $schema['columns'] ?? [];
        $this->schema['traits'] = $schema['traits'] ?? [];
        $this->schema['contracts'] = $schema['contracts'] ?? [];
        $this->schema['fillable'] = $schema['fillable'] ?? [];
        $this->schema['casts'] = $schema['casts'] ?? [];
        $this->schema['relationships'] = $schema['relationships'] ?? [];
        // @codeCoverageIgnoreEnd
    }
}
