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

/**
 * CreateEntityTool.
 */
class CreateEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return 'create_'.Str::snake(class_basename($this->modelClass));
    }

    public function title(): string
    {
        return "Create {$this->entityLabel}";
    }

    public function description(): string
    {
        $fillable = (new $this->modelClass)->getFillable();
        $fieldList = implode(', ', $fillable);

        return "Create a new {$this->entityLabel}. Fillable fields: {$fieldList}";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $instance = new $this->modelClass;
        $fillable = $instance->getFillable();
        $properties = [];

        foreach ($fillable as $field) {
            $properties[$field] = $schema->string()->description("The {$field} value");
        }

        return $properties;
    }

    /** @codeCoverageIgnore Reason: mcp-runtime -- Form request validation requires live MCP request context */
    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $scopeError = $this->checkScope($request, 'write');

        if ($scopeError) {
            return $scopeError;
        }

        $user = $request->user('api');

        if (! $user || ! $user->can('create', $this->modelClass)) {
            return Response::error("Unauthorized to create {$this->entityLabel}.");
        }

        $instance = new $this->modelClass;
        $fillable = $instance->getFillable();

        // Validate via Form Request rules (authorize() is intentionally
        // skipped — a bare `new` bypasses the IoC container so `$this->user()`
        // inside authorize() is null. The policy check above is the real
        // authorization guard.
        $formRequestClass = $this->resolveFormRequest('Store');

        if ($formRequestClass) {
            $formRequest = new $formRequestClass;

            if (method_exists($formRequest, 'rules')) {
                $request->validate($formRequest->rules());
            }
        }

        $data = array_intersect_key($request->only($fillable), array_flip($fillable));

        $model = $this->modelClass::create($data);

        return Response::json([
            'message' => "{$this->entityLabel} created successfully.",
            'data' => $model->toArray(),
        ]);
        // @codeCoverageIgnoreEnd
    }

    protected function resolveFormRequest(string $prefix): ?string
    {
        $basename = class_basename($this->modelClass);
        $candidates = [
            "App\\Http\\Requests\\{$prefix}{$basename}Request",
            "App\\Http\\Requests\\{$basename}\\{$prefix}{$basename}Request",
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                // @codeCoverageIgnoreStart — MCP server integration
                return $candidate;
                // @codeCoverageIgnoreEnd
            }
        }

        return null;
    }
}
