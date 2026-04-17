<?php

declare(strict_types=1);

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** MCP tool that deletes a single entity record by ID with scope-based authorization. */
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
        // @codeCoverageIgnoreStart — MCP server integration
        $scopeError = $this->checkScope($request, 'delete');

        if ($scopeError) {
            return $scopeError;
        }

        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        /** @var Model|null $model */
        $model = $this->modelClass::find($validated['id']);

        if (! $model) {
            return Response::error("{$this->entityLabel} not found.");
        }

        $user = $request->user('api');

        if (! $user || ! $user->can('delete', $model)) {
            // Unified error to prevent existence enumeration via differential
            // error responses. Policy denials are logged for audit trail.
            Log::info('MCP policy denial', [
                'tool' => static::class,
                'model' => $this->modelClass,
                'model_id' => $validated['id'],
                'user_id' => $user?->getAuthIdentifier(),
            ]);

            return Response::error("{$this->entityLabel} not found.");
        }

        $model->delete();

        return Response::json([
            'message' => "{$this->entityLabel} deleted successfully.",
            'id' => $validated['id'],
        ]);
        // @codeCoverageIgnoreEnd
    }
}
