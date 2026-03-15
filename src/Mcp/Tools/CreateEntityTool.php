<?php

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

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

    public function handle(Request $request): Response
    {
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

        $data = array_intersect_key($request->all(), array_flip($fillable));

        // Use Form Request validation if available
        $formRequestClass = $this->resolveFormRequest('Store');

        if ($formRequestClass) {
            $formRequest = new $formRequestClass;

            if (method_exists($formRequest, 'rules')) {
                $request->validate($formRequest->rules());
            }
        }

        $model = $this->modelClass::create($data);

        return Response::json([
            'message' => "{$this->entityLabel} created successfully.",
            'data' => $model->toArray(),
        ]);
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
                return $candidate;
            }
        }

        return null;
    }
}
