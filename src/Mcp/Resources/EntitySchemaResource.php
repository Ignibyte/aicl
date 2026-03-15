<?php

namespace Aicl\Mcp\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use ReflectionClass;
use ReflectionMethod;

class EntitySchemaResource extends Resource
{
    protected string $mimeType = 'application/json';

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return Str::snake(class_basename($this->modelClass)).'_schema';
    }

    public function title(): string
    {
        return "{$this->entityLabel} Schema";
    }

    public function description(): string
    {
        return "Schema definition for the {$this->entityLabel} entity including fields, types, relationships, and states.";
    }

    public function uri(): string
    {
        return 'entity://'.Str::snake(class_basename($this->modelClass)).'/schema';
    }

    public function handle(Request $request): Response
    {
        /** @var Model $instance */
        $instance = new $this->modelClass;

        $schema = [
            'entity' => $this->entityLabel,
            'model' => $this->modelClass,
            'table' => $instance->getTable(),
            'fillable' => $instance->getFillable(),
            'casts' => $this->resolveCasts($instance),
            'relationships' => $this->discoverRelationships(),
        ];

        $states = $this->discoverStates();
        if (! empty($states)) {
            $schema['states'] = $states;
        }

        return Response::json($schema);
    }

    /**
     * Resolve the model's cast definitions into a simple type map.
     *
     * @return array<string, string>
     */
    protected function resolveCasts(Model $instance): array
    {
        $casts = $instance->getCasts();
        $resolved = [];

        foreach ($casts as $field => $cast) {
            $resolved[$field] = is_object($cast) ? get_class($cast) : (string) $cast;
        }

        return $resolved;
    }

    /**
     * Discover relationship methods by inspecting the model class for
     * methods that return Eloquent relationship instances.
     *
     * @return array<string, string>
     */
    protected function discoverRelationships(): array
    {
        $relationships = [];
        $reflection = new ReflectionClass($this->modelClass);
        $relationTypes = [
            'Illuminate\Database\Eloquent\Relations\HasOne',
            'Illuminate\Database\Eloquent\Relations\HasMany',
            'Illuminate\Database\Eloquent\Relations\BelongsTo',
            'Illuminate\Database\Eloquent\Relations\BelongsToMany',
            'Illuminate\Database\Eloquent\Relations\HasOneThrough',
            'Illuminate\Database\Eloquent\Relations\HasManyThrough',
            'Illuminate\Database\Eloquent\Relations\MorphOne',
            'Illuminate\Database\Eloquent\Relations\MorphMany',
            'Illuminate\Database\Eloquent\Relations\MorphTo',
            'Illuminate\Database\Eloquent\Relations\MorphToMany',
        ];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $this->modelClass) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();

            foreach ($relationTypes as $relationType) {
                if ($typeName === $relationType || is_subclass_of($typeName, $relationType)) {
                    $relationships[$method->getName()] = class_basename($typeName);
                    break;
                }
            }
        }

        return $relationships;
    }

    /**
     * Discover state names if the model uses spatie/laravel-model-states.
     *
     * @return array<int, string>
     */
    protected function discoverStates(): array
    {
        if (! method_exists($this->modelClass, 'getStates')) {
            return [];
        }

        try {
            $states = $this->modelClass::getStates();

            return array_values(array_map(fn ($state): string => (string) $state, $states));
        } catch (\Throwable) {
            return [];
        }
    }
}
