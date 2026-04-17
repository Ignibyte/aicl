<?php

declare(strict_types=1);

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** MCP tool that updates an existing entity record with provided field values. */
class UpdateEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return 'update_'.Str::snake(class_basename($this->modelClass));
    }

    public function title(): string
    {
        return "Update {$this->entityLabel}";
    }

    public function description(): string
    {
        $fillable = (new $this->modelClass)->getFillable();
        $fieldList = implode(', ', $fillable);

        return "Update an existing {$this->entityLabel}. Updatable fields: {$fieldList}";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $instance = new $this->modelClass;
        $fillable = $instance->getFillable();
        $properties = [
            'id' => $schema->string()->description("The {$this->entityLabel} ID")->required(),
        ];

        foreach ($fillable as $field) {
            $properties[$field] = $schema->string()->description("The {$field} value");
        }

        return $properties;
    }

    /**
     * @codeCoverageIgnore Reason: mcp-runtime -- Form request validation requires live MCP request context
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $scopeError = $this->checkScope($request, 'write');

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

        if (! $user || ! $user->can('update', $model)) {
            return Response::error("Unauthorized to update this {$this->entityLabel}.");
        }

        $fillable = $model->getFillable();

        // Validate via Form Request rules (authorize() is intentionally
        // skipped — a bare `new` bypasses the IoC container so `$this->user()`
        // inside authorize() is null. The policy check above is the real
        // authorization guard.
        $formRequestClass = $this->resolveFormRequest('Update');

        if ($formRequestClass) {
            $formRequest = new $formRequestClass;

            if (method_exists($formRequest, 'rules')) {
                $request->validate($formRequest->rules());
            }
        }

        $data = array_intersect_key($request->only($fillable), array_flip($fillable));

        $model->update($data);

        $freshModel = $model->fresh();

        return Response::json([
            'message' => "{$this->entityLabel} updated successfully.",
            'data' => $freshModel ? $freshModel->toArray() : $model->toArray(),
        ]);
        // @codeCoverageIgnoreEnd
    }

    protected function resolveFormRequest(string $prefix): ?string
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $basename = class_basename($this->modelClass);
        $candidates = [
            "App\\Http\\Requests\\{$prefix}{$basename}Request",
            "App\\Http\\Requests\\{$basename}\\{$prefix}{$basename}Request",
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
        // @codeCoverageIgnoreEnd
    }
}
