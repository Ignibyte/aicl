<?php

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return 'delete_'.Str::snake(class_basename($this->modelClass));
    }

    public function title(): string
    {
        return "Delete {$this->entityLabel}";
    }

    public function description(): string
    {
        return "Delete a {$this->entityLabel} record by ID.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description("The {$this->entityLabel} ID")->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $scopeError = $this->checkScope($request, 'delete');

        if ($scopeError) {
            return $scopeError;
        }

        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $model = $this->modelClass::find($validated['id']);

        if (! $model) {
            return Response::error("{$this->entityLabel} not found.");
        }

        $user = $request->user('api');

        if (! $user || ! $user->can('delete', $model)) {
            return Response::error("Unauthorized to delete this {$this->entityLabel}.");
        }

        $model->delete();

        return Response::json([
            'message' => "{$this->entityLabel} deleted successfully.",
            'id' => $validated['id'],
        ]);
    }
}
