<?php

declare(strict_types=1);

namespace Aicl\Mcp\Prompts;

use Aicl\Services\EntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/** MCP prompt that guides AI agents through CRUD operations on registered entities. */
class CrudWorkflowPrompt extends Prompt
{
    protected string $name = 'crud_workflow';

    protected string $description = 'Guide through creating, updating, or managing an entity.';

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'entity_type',
                description: 'The entity type to operate on (snake_case, e.g. "user", "blog_post").',
                required: true,
            ),
            new Argument(
                name: 'operation',
                description: 'The CRUD operation: create, update, list, or delete. Defaults to "create".',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $entityType = $request->get('entity_type');
        $operation = $request->get('operation', 'create');

        /** @var EntityRegistry $registry */
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        $entry = $entities->first(
            fn (array $entry): bool => Str::snake(class_basename($entry['class'])) === $entityType
        );

        if (! $entry) {
            return Response::error("Entity type '{$entityType}' not found. Available types: "
                .implode(', ', $entities->map(fn (array $e): string => Str::snake(class_basename($e['class'])))->all()));
        }

        /** @var Model $instance */
        $instance = new $entry['class'];
        $fillable = $instance->getFillable();
        $casts = $instance->getCasts();
        $label = $entry['label'];
        $hasStatus = $entry['columns']['has_status'];

        $instructions = $this->buildInstructions($label, $operation, $fillable, $casts, $hasStatus);

        return Response::text($instructions);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Build workflow instructions for the given operation.
     *
     * @param array<int, string>    $fillable
     * @param array<string, string> $casts
     */
    protected function buildInstructions(
        string $label,
        string $operation,
        array $fillable,
        array $casts,
        bool $hasStatus,
    ): string {
        // @codeCoverageIgnoreStart — MCP server integration
        $fieldList = implode(', ', $fillable);
        $castInfo = '';
        foreach ($casts as $field => $type) {
            $castInfo .= "\n  - {$field}: {$type}";
        }

        $header = "## {$label} — ".ucfirst($operation)." Workflow\n\n";

        $common = "### Available Fields\n{$fieldList}\n\n"
            ."### Field Types{$castInfo}\n\n";

        if ($hasStatus) {
            $common .= "### Note\nThis entity has a status field. Use the transition tool to change states.\n\n";
        }

        return match ($operation) {
            'create' => $header.$common
                ."### Steps\n"
                ."1. Gather required field values from the user.\n"
                .'2. Call the `create_'.Str::snake($label)."` tool with the field data.\n"
                ."3. Confirm creation and return the new record ID.\n",

            'update' => $header.$common
                ."### Steps\n"
                .'1. Identify the record by ID (use `list_'.Str::plural(Str::snake($label))."` if needed).\n"
                .'2. Show current values with `show_'.Str::snake($label)."`.\n"
                ."3. Collect updated field values.\n"
                .'4. Call `update_'.Str::snake($label)."` with the ID and changed fields.\n",

            'list' => $header.$common
                ."### Steps\n"
                .'1. Call `list_'.Str::plural(Str::snake($label))."` with optional filters.\n"
                ."2. Present results in a readable format.\n"
                ."3. Offer pagination if more records exist.\n",

            'delete' => $header.$common
                ."### Steps\n"
                ."1. Identify the record by ID.\n"
                .'2. Show the record details for confirmation with `show_'.Str::snake($label)."`.\n"
                ."3. Confirm deletion with the user.\n"
                .'4. Call `delete_'.Str::snake($label)."` with the ID.\n",

            default => $header.$common
                ."### Available Operations\ncreate, update, list, delete\n"
                ."Specify an operation for detailed instructions.\n",
        };
        // @codeCoverageIgnoreEnd
    }
}
