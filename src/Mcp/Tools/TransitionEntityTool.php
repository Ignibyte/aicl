<?php

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStates\State;

class TransitionEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    public function name(): string
    {
        return 'transition_'.Str::snake(class_basename($this->modelClass));
    }

    public function title(): string
    {
        return "Transition {$this->entityLabel} State";
    }

    public function description(): string
    {
        $states = $this->getAvailableStates();
        $stateList = implode(', ', $states);

        return "Transition a {$this->entityLabel} to a new state. Available states: {$stateList}";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $states = $this->getAvailableStates();

        return [
            'id' => $schema->string()->description("The {$this->entityLabel} ID")->required(),
            'to' => $schema->string()->description('Target state')->enum($states)->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $scopeError = $this->checkScope($request, 'transitions');

        if ($scopeError) {
            return $scopeError;
        }

        $validated = $request->validate([
            'id' => 'required|string',
            'to' => 'required|string',
        ]);

        $model = $this->modelClass::find($validated['id']);

        if (! $model) {
            return Response::error("{$this->entityLabel} not found.");
        }

        $user = $request->user('api');

        if (! $user || ! $user->can('update', $model)) {
            return Response::error("Unauthorized to transition this {$this->entityLabel}.");
        }

        if (! in_array(HasStates::class, class_uses_recursive($this->modelClass), true)) {
            return Response::error("{$this->entityLabel} does not support state transitions.");
        }

        $targetState = $validated['to'];

        try {
            $model->status->transitionTo($this->resolveStateClass($targetState)); // @phpstan-ignore property.notFound
            $model->refresh();

            return Response::json([
                'message' => "{$this->entityLabel} transitioned to {$targetState}.",
                'data' => $model->toArray(),
            ]);
        } catch (TransitionNotFound $e) {
            return Response::error("Cannot transition to '{$targetState}' from current state.");
        } catch (\Throwable $e) {
            return Response::error("Transition failed: {$e->getMessage()}");
        }
    }

    /** @return array<string> */
    protected function getAvailableStates(): array
    {
        if (! in_array(HasStates::class, class_uses_recursive($this->modelClass), true)) {
            return [];
        }

        $instance = new $this->modelClass;

        $stateConfig = $instance->getStatesFor('status'); // @phpstan-ignore method.notFound

        if (! $stateConfig) {
            return [];
        }

        /** @var array<int, string> $allowedStates */
        $allowedStates = $stateConfig->allowedStates();

        return collect($allowedStates)
            ->map(fn (string $stateClass): string => $this->resolveStateName($stateClass))
            ->values()
            ->toArray();
    }

    protected function resolveStateName(string $stateClass): string
    {
        if (is_subclass_of($stateClass, State::class)) {
            return $stateClass::$name ?? class_basename($stateClass);
        }

        return class_basename($stateClass);
    }

    protected function resolveStateClass(string $name): string
    {
        $instance = new $this->modelClass;
        $stateConfig = $instance->getStatesFor('status'); // @phpstan-ignore method.notFound

        if (! $stateConfig) {
            return $name;
        }

        foreach ($stateConfig->allowedStates() as $stateClass) {
            $stateName = $this->resolveStateName($stateClass);
            if ($stateName === $name) {
                return $stateClass;
            }
        }

        return $name;
    }
}
