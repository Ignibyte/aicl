<?php

declare(strict_types=1);

namespace Aicl\Mcp\Tools;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStates\State;

/**
 * MCP tool that transitions an entity's state machine to a new state.
 *
 * Discovers the model's state column dynamically from its casts array
 * (the first cast key whose value is a Spatie State subclass) and uses
 * the Spatie v4 API (getStatesFor / getStateMapping) for state enumeration.
 *
 * @codeCoverageIgnore Reason: mcp-runtime -- State transition requires live entity with state machine
 */
class TransitionEntityTool extends Tool
{
    use ChecksTokenScope;

    public function __construct(
        /** @var class-string<Model> */
        protected string $modelClass,
        protected string $entityLabel,
    ) {}

    /**
     * Return the MCP tool name in snake_case with transition_ prefix.
     */
    public function name(): string
    {
        return 'transition_'.Str::snake(class_basename($this->modelClass));
    }

    /**
     * Return the human-readable tool title.
     */
    public function title(): string
    {
        return "Transition {$this->entityLabel} State";
    }

    /**
     * Return the tool description including the list of available states.
     */
    public function description(): string
    {
        $states = $this->getAvailableStates();
        $stateList = implode(', ', $states);

        return "Transition a {$this->entityLabel} to a new state. Available states: {$stateList}";
    }

    /**
     * Define the MCP input schema with id and to fields.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $states = $this->getAvailableStates();

        return [
            'id' => $schema->string()->description("The {$this->entityLabel} ID")->required(),
            'to' => $schema->string()->description('Target state')->enum($states)->required(),
        ];
    }

    /**
     * Handle the state transition request.
     *
     * Validates the request, checks authorization, discovers the model's
     * state column dynamically, and performs the transition using Spatie's
     * State::transitionTo() API.
     */
    public function handle(Request $request): Response
    {
        // @codeCoverageIgnoreStart — MCP server integration
        $scopeError = $this->checkScope($request, 'transitions');

        if ($scopeError) {
            return $scopeError;
        }

        $validated = $request->validate([
            'id' => 'required|string',
            'to' => 'required|string',
        ]);

        /** @var Model|null $model */
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

        // Discover the state column dynamically from the model's casts
        $stateField = $this->resolveStateField();

        if ($stateField === null) {
            return Response::error("{$this->entityLabel} has no configured state field.");
        }

        $targetState = $validated['to'];

        try {
            // Access the state property dynamically using the discovered column name
            $model->{$stateField}->transitionTo($this->resolveStateClass($targetState));
            $model->refresh();

            return Response::json([
                'message' => "{$this->entityLabel} transitioned to {$targetState}.",
                'data' => $model->toArray(),
            ]);
        } catch (TransitionNotFound|CouldNotPerformTransition $e) {
            return Response::error("Cannot transition to '{$targetState}' from current state.");
        } catch (\Throwable $e) {
            return Response::error("Transition failed: {$e->getMessage()}");
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get the list of available state names for the model.
     *
     * Uses the base state class's getStateMapping() method (Spatie v4) which
     * returns a Collection keyed by morph name with FQCN values. Each FQCN
     * is resolved to its human-readable display name via resolveStateName().
     *
     * @return array<string>
     */
    protected function getAvailableStates(): array
    {
        if (! in_array(HasStates::class, class_uses_recursive($this->modelClass), true)) {
            return [];
        }

        // @codeCoverageIgnoreStart — MCP server integration
        $baseStateClass = $this->resolveBaseStateClass();

        if ($baseStateClass === null) {
            return [];
            // @codeCoverageIgnoreEnd
        }

        // Spatie v4: getStateMapping() returns Collection<morph_name, fqcn>
        // The values are fully-qualified state class names
        /** @var Collection<string, class-string<State<Model>>> $stateMapping */
        // @codeCoverageIgnoreStart — MCP server integration
        $stateMapping = $baseStateClass::getStateMapping();

        if ($stateMapping->isEmpty()) {
            return [];
        }

        // Resolve each FQCN to its human-readable display name
        return $stateMapping
            ->map(fn (string $stateClass): string => $this->resolveStateName($stateClass))
            ->values()
            ->toArray();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve a state class FQCN to its human-readable display name.
     *
     * For State subclasses, uses the static $name property (morph class name)
     * if available, otherwise falls back to the class basename.
     */
    protected function resolveStateName(string $stateClass): string
    {
        // @codeCoverageIgnoreStart — MCP server integration
        if (is_subclass_of($stateClass, State::class)) {
            return $stateClass::$name ?? class_basename($stateClass);
        }

        return class_basename($stateClass);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve a human-readable state name back to its FQCN state class.
     *
     * Iterates through the base state class's getStateMapping() to find the
     * class whose display name matches the given name (case-insensitive for
     * robust MCP tool usage). Returns the raw name if no match is found,
     * allowing Spatie to attempt its own resolution.
     */
    protected function resolveStateClass(string $name): string
    {
        // @codeCoverageIgnoreStart — MCP server integration
        if (! in_array(HasStates::class, class_uses_recursive($this->modelClass), true)) {
            return $name;
        }

        $baseStateClass = $this->resolveBaseStateClass();

        if ($baseStateClass === null) {
            return $name;
            // @codeCoverageIgnoreEnd
        }

        // Spatie v4: getStateMapping() returns Collection<morph_name, fqcn>
        /** @var Collection<string, class-string<State<Model>>> $stateMapping */
        // @codeCoverageIgnoreStart — MCP server integration
        $stateMapping = $baseStateClass::getStateMapping();

        foreach ($stateMapping as $stateClass) {
            $stateName = $this->resolveStateName($stateClass);
            // Case-insensitive comparison for robust MCP tool input handling
            if (strcasecmp($stateName, $name) === 0) {
                return $stateClass;
            }
        }

        return $name;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Discover the model's state column name from its casts.
     *
     * Iterates through the model's casts array and returns the first key
     * whose value is a subclass of Spatie's State base class. This allows
     * the tool to work with any state column name (e.g. 'state', 'status',
     * 'workflow_state') without hardcoding.
     *
     * @return string|null The state column name, or null if no state cast is found.
     */
    protected function resolveStateField(): ?string
    {
        /** @var Model $instance */
        // @codeCoverageIgnoreStart — MCP server integration
        $instance = new $this->modelClass;
        $casts = $instance->getCasts();

        foreach ($casts as $field => $castClass) {
            if (is_string($castClass) && is_subclass_of($castClass, State::class)) {
                return $field;
            }
        }

        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Discover the base State class from the model's casts.
     *
     * Returns the FQCN of the State subclass used in the model's casts,
     * which can be used to call static methods like getStateMapping().
     *
     * @return class-string<State<Model>>|null The base state class, or null if no state cast is found.
     */
    protected function resolveBaseStateClass(): ?string
    {
        /** @var Model $instance */
        // @codeCoverageIgnoreStart — MCP server integration
        $instance = new $this->modelClass;
        $casts = $instance->getCasts();

        foreach ($casts as $castClass) {
            if (is_string($castClass) && is_subclass_of($castClass, State::class)) {
                return $castClass;
            }
        }

        return null;
        // @codeCoverageIgnoreEnd
    }
}
