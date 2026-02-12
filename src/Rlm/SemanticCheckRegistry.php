<?php

namespace Aicl\Rlm;

class SemanticCheckRegistry
{
    /**
     * @return SemanticCheck[]
     */
    public static function all(): array
    {
        return [
            ...self::factoryChecks(),
            ...self::validationChecks(),
            ...self::authorizationChecks(),
            ...self::apiChecks(),
            ...self::testChecks(),
            ...self::modelChecks(),
            ...self::widgetChecks(),
            ...self::stateChecks(),
        ];
    }

    /**
     * Return only checks applicable to the given entity context.
     *
     * @param  array<string, mixed>  $entityContext
     * @return SemanticCheck[]
     */
    public static function applicable(array $entityContext = []): array
    {
        return array_values(array_filter(
            self::all(),
            fn (SemanticCheck $check): bool => $check->isApplicable($entityContext),
        ));
    }

    /**
     * @return SemanticCheck[]
     */
    private static function factoryChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.factory_types',
                description: 'Factory generates correct types for migration columns',
                targets: ['migration', 'factory'],
                prompt: <<<'PROMPT'
                    Compare the migration columns with the factory's definition() method.

                    For each column defined in the migration, verify the factory generates a value of the correct PHP type:
                    - boolean columns should use fake()->boolean(), NOT fake()->sentence() or fake()->word()
                    - integer/float columns should use fake()->randomNumber() or fake()->randomFloat(), NOT fake()->word()
                    - date/datetime columns should use fake()->date() or fake()->dateTime() or Carbon::now(), NOT fake()->sentence()
                    - text/string columns may use fake()->sentence(), fake()->paragraph(), fake()->word(), etc.
                    - enum columns should use fake()->randomElement() with the correct enum cases
                    - json columns should use arrays or json_encode(), NOT fake()->sentence()
                    - foreignId columns should use a factory or random integer, NOT fake()->sentence()

                    Report PASS if all column types match their factory definitions correctly.
                    Report FAIL if any column has a type mismatch, listing each mismatch.
                    PROMPT,
                severity: 'error',
                weight: 2.0,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function validationChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.validation_nullability',
                description: 'Form request rules match migration nullability',
                targets: ['migration', 'form_request'],
                prompt: <<<'PROMPT'
                    Compare the migration column definitions with the form request validation rules.

                    For each column in the migration:
                    - If the column is NOT nullable (no ->nullable() call), the form request should have a 'required' rule
                    - If the column IS nullable (has ->nullable()), the form request should have a 'nullable' rule or omit the field
                    - Ignore: id, timestamps (created_at, updated_at), deleted_at, owner_id (these are auto-managed)

                    Report PASS if nullability constraints are consistent between migration and form request.
                    Report FAIL if any mismatch is found, listing each one.
                    PROMPT,
                severity: 'warning',
                weight: 1.5,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function authorizationChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.authorization_coverage',
                description: 'Controller actions call authorize() with correct policy methods',
                targets: ['controller', 'policy'],
                prompt: <<<'PROMPT'
                    Compare the API controller actions with the policy methods.

                    For each controller method that modifies data (store, update, destroy):
                    1. Verify it calls $this->authorize(), Gate::authorize(), or uses a FormRequest with authorize()
                    2. Verify the authorization check references the correct policy method name

                    For read methods (index, show):
                    1. Verify they have some form of authorization (policy check, middleware, or gate)

                    Report PASS if every controller action that modifies data has proper authorization.
                    Report FAIL if any action is missing authorization, listing each unprotected action.
                    PROMPT,
                severity: 'error',
                weight: 2.0,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function apiChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.resource_exposure',
                description: 'API resource does not expose sensitive fields',
                targets: ['api_resource', 'model'],
                prompt: <<<'PROMPT'
                    Examine the API Resource's toArray() method and the model's $hidden/$visible properties.

                    Check if the API resource exposes any sensitive fields without conditional hiding:
                    - password, password_hash, remember_token, secret, api_key, access_token
                    - Any field containing "password", "secret", "token", "key", "hash" in its name

                    Acceptable patterns:
                    - Field is in model's $hidden array
                    - Field is wrapped in $this->when() or conditional logic
                    - Field is not present in the resource at all

                    Report PASS if no sensitive fields are exposed unconditionally.
                    Report FAIL if any sensitive field is exposed without protection, listing each one.
                    PROMPT,
                severity: 'error',
                weight: 2.0,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function testChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.test_coverage',
                description: 'Tests cover model relationships, scopes, casts, and policies',
                targets: ['model', 'test'],
                prompt: <<<'PROMPT'
                    Compare the model's features with the test file's test methods.

                    Check that the test file includes tests for:
                    1. Each relationship method defined on the model (belongsTo, hasMany, etc.)
                    2. Each scope method (scopeActive, scopeSearch, etc.)
                    3. Soft delete behavior (if SoftDeletes trait is used)
                    4. At least one policy/authorization test
                    5. Factory creation and database persistence

                    Report PASS if all major model features have corresponding tests.
                    Report FAIL if any relationship, scope, or critical behavior lacks a test, listing each gap.
                    PROMPT,
                severity: 'warning',
                weight: 1.5,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function modelChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.searchable_columns',
                description: 'searchableColumns() references only columns that exist in migration',
                targets: ['model', 'migration'],
                prompt: <<<'PROMPT'
                    Check if the model overrides or defines searchableColumns().

                    If it does:
                    1. Extract the list of column names returned by searchableColumns()
                    2. Verify each column exists in the migration's up() method
                    3. Check that no column name is misspelled or references a non-existent column

                    If the model does NOT override searchableColumns(), report PASS (defaults are handled elsewhere).

                    Report PASS if all searchable columns exist in the migration.
                    Report FAIL if any searchable column does not exist in the migration, listing each missing column.
                    PROMPT,
                severity: 'error',
                weight: 2.0,
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function widgetChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.widget_queries',
                description: 'Widgets reference only existing columns and relationships',
                targets: ['widget', 'model', 'migration'],
                prompt: <<<'PROMPT'
                    Examine the widget class and check that all database queries reference valid columns.

                    For each query in the widget (Eloquent calls, DB queries, column references):
                    1. Verify referenced columns exist in the migration
                    2. Verify referenced relationships exist on the model
                    3. Check that aggregate functions (count, sum, avg) reference valid columns

                    Report PASS if all widget queries reference valid columns and relationships.
                    Report FAIL if any query references a non-existent column or relationship, listing each one.
                    PROMPT,
                severity: 'warning',
                weight: 1.0,
                appliesWhen: 'has_widgets',
            ),
        ];
    }

    /**
     * @return SemanticCheck[]
     */
    private static function stateChecks(): array
    {
        return [
            new SemanticCheck(
                name: 'semantic.state_transitions',
                description: 'State transitions are tested for both valid and invalid paths',
                targets: ['states', 'test'],
                prompt: <<<'PROMPT'
                    Examine the state classes and the test file.

                    For each state class that defines allowedTransitions():
                    1. List all valid transitions (from state → to state)
                    2. Check the test file covers at least one test per valid transition
                    3. Check the test file has at least one test for an invalid/disallowed transition

                    Report PASS if all valid transitions have tests and at least one invalid transition is tested.
                    Report FAIL if any valid transition lacks a test, or if no invalid transition test exists.
                    PROMPT,
                severity: 'warning',
                weight: 1.5,
                appliesWhen: 'has_states',
            ),
        ];
    }
}
