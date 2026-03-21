<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\States\AiAgent\AiAgentState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiAgent model PHPStan changes.
 *
 * Covers typed property declarations, cast definitions, scope signatures,
 * accessor return types, visibility helper null guards, tools capability
 * methods, and searchableColumns override. These changes were introduced
 * during the PHPStan level 5-to-8 migration.
 */
class AiAgentRegressionTest extends TestCase
{
    /**
     * Test that the model uses the expected traits.
     *
     * Verifies trait composition is correct after PHPStan migration
     * added generic template annotations to HasFactory.
     */
    public function test_model_uses_expected_traits(): void
    {
        // Arrange: get all traits used by the model (including parent traits)
        $traits = class_uses_recursive(AiAgent::class);

        // Assert: verify core traits are present
        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayHasKey(SoftDeletes::class, $traits);
    }

    /**
     * Test that fillable contains all expected attributes.
     *
     * PHPStan migration added typed property annotations; this verifies
     * the fillable array matches the declared properties.
     */
    public function test_fillable_contains_all_expected_attributes(): void
    {
        // Arrange
        $agent = new AiAgent;

        // Act
        $fillable = $agent->getFillable();

        // Assert: verify all key fillable attributes
        $expected = [
            'name', 'slug', 'description', 'provider', 'model',
            'system_prompt', 'max_tokens', 'temperature', 'context_window',
            'context_messages', 'is_active', 'icon', 'color', 'sort_order',
            'suggested_prompts', 'capabilities', 'visible_to_roles',
            'max_requests_per_minute', 'state',
        ];

        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $fillable, "Missing fillable: {$attribute}");
        }
    }

    /**
     * Test that casts method returns expected cast definitions.
     *
     * PHPStan added @return array<string, string> annotation to casts().
     * This verifies the cast map is correct.
     */
    public function test_casts_returns_expected_definitions(): void
    {
        // Arrange
        $agent = new AiAgent;

        // Act: use reflection to call protected casts() method
        $reflection = new \ReflectionMethod($agent, 'casts');
        $casts = $reflection->invoke($agent);

        // Assert: verify key cast definitions
        $this->assertSame(AiProvider::class, $casts['provider']);
        $this->assertSame(AiAgentState::class, $casts['state']);
        $this->assertSame('integer', $casts['max_tokens']);
        $this->assertSame('decimal:2', $casts['temperature']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('array', $casts['suggested_prompts']);
        $this->assertSame('array', $casts['capabilities']);
        $this->assertSame('array', $casts['visible_to_roles']);
    }

    /**
     * Test conversations relationship method returns HasMany type.
     *
     * PHPStan added generic @return HasMany<AiConversation, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_conversations_relationship_returns_has_many(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiAgent::class, 'conversations');
        $returnType = $method->getReturnType();

        // Assert: method returns HasMany
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(HasMany::class, $returnType->getName());
    }

    /**
     * Test getDisplayNameAttribute returns the name property.
     *
     * PHPStan enforced string return type on this accessor.
     */
    public function test_display_name_attribute_returns_name(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->name = 'Test Agent';

        // Act
        $displayName = $agent->getDisplayNameAttribute();

        // Assert
        $this->assertSame('Test Agent', $displayName);
    }

    /**
     * Test searchableColumns returns custom columns (not default).
     *
     * Override prevents BF-001/BF-005 (models without 'title' column
     * would get QueryException with default searchableColumns).
     */
    public function test_searchable_columns_returns_custom_columns(): void
    {
        // Arrange
        $agent = new AiAgent;

        // Act: use reflection to call protected searchableColumns()
        $reflection = new \ReflectionMethod($agent, 'searchableColumns');
        $columns = $reflection->invoke($agent);

        // Assert: must include name, slug, description — NOT 'title'
        $this->assertSame(['name', 'slug', 'description'], $columns);
    }

    /**
     * Test isVisibleTo with null visible_to_roles returns true.
     *
     * PHPStan enforced strict null check: $this->visible_to_roles === null.
     */
    public function test_is_visible_to_returns_true_when_roles_null(): void
    {
        // Arrange: agent with null visible_to_roles
        $agent = new AiAgent;
        $agent->visible_to_roles = null;

        // Act
        $result = $agent->isVisibleTo(['admin']);

        // Assert: null roles means visible to everyone
        $this->assertTrue($result);
    }

    /**
     * Test isVisibleTo with empty array returns true.
     *
     * PHPStan enforced strict === [] comparison.
     */
    public function test_is_visible_to_returns_true_when_roles_empty(): void
    {
        // Arrange: agent with empty visible_to_roles
        $agent = new AiAgent;
        $agent->visible_to_roles = [];

        // Act
        $result = $agent->isVisibleTo(['admin']);

        // Assert: empty roles means visible to everyone
        $this->assertTrue($result);
    }

    /**
     * Test isVisibleTo returns true when user has matching role.
     *
     * Verifies the array_intersect logic works correctly with strict types.
     */
    public function test_is_visible_to_returns_true_for_matching_role(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->visible_to_roles = ['admin', 'editor'];

        // Act
        $result = $agent->isVisibleTo(['admin']);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isVisibleTo returns false when no role matches.
     *
     * Verifies the count(array_intersect) > 0 check.
     */
    public function test_is_visible_to_returns_false_for_non_matching_role(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->visible_to_roles = ['admin', 'editor'];

        // Act
        $result = $agent->isVisibleTo(['viewer']);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isAccessibleByUser returns false for null user.
     *
     * PHPStan migration added nullable type hint: ?User $user.
     */
    public function test_is_accessible_by_user_returns_false_for_null(): void
    {
        // Arrange
        $agent = new AiAgent;

        // Act
        $result = $agent->isAccessibleByUser(null);

        // Assert: null user cannot access anything
        $this->assertFalse($result);
    }

    /**
     * Test isAccessibleByUser returns true when visible_to_roles is null.
     *
     * Null roles = no restrictions = accessible to all authenticated users.
     */
    public function test_is_accessible_by_user_returns_true_for_unrestricted(): void
    {
        // Arrange: create a minimal user mock without getRoleNames
        $agent = new AiAgent;
        $agent->visible_to_roles = null;

        // Create a basic user without Spatie HasRoles trait
        $user = new class extends User {};

        // Act
        $result = $agent->isAccessibleByUser($user);

        // Assert: no restrictions = accessible
        $this->assertTrue($result);
    }

    /**
     * Test isAccessibleByUser returns true when user lacks getRoleNames.
     *
     * When the user model doesn't use HasRoles trait, method_exists
     * check returns false and the method returns true (permissive).
     */
    public function test_is_accessible_by_user_without_role_method(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->visible_to_roles = ['admin'];

        // User without Spatie HasRoles trait
        $user = new class extends User {};

        // Act
        $result = $agent->isAccessibleByUser($user);

        // Assert: no role method = permissive (returns true)
        $this->assertTrue($result);
    }

    /**
     * Test hasToolsEnabled returns false when capabilities is not array.
     *
     * PHPStan enforced is_array guard: if (! is_array($capabilities)).
     */
    public function test_has_tools_enabled_returns_false_when_capabilities_null(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = null;

        // Act
        $result = $agent->hasToolsEnabled();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasToolsEnabled returns false when tools_enabled is absent.
     *
     * Null coalescing: $capabilities['tools_enabled'] ?? false.
     */
    public function test_has_tools_enabled_returns_false_when_key_missing(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = ['some_other_key' => true];

        // Act
        $result = $agent->hasToolsEnabled();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasToolsEnabled returns true when tools are enabled.
     */
    public function test_has_tools_enabled_returns_true_when_enabled(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = ['tools_enabled' => true];

        // Act
        $result = $agent->hasToolsEnabled();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test getAllowedTools returns empty array when tools disabled.
     *
     * With tools disabled, returns [] (not null).
     */
    public function test_get_allowed_tools_returns_empty_when_tools_disabled(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = null;

        // Act
        $result = $agent->getAllowedTools();

        // Assert: returns empty array (tools disabled)
        $this->assertSame([], $result);
    }

    /**
     * Test getAllowedTools returns null when no restrictions.
     *
     * null = all registered tools allowed (no restriction).
     */
    public function test_get_allowed_tools_returns_null_for_unrestricted(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = ['tools_enabled' => true];

        // Act
        $result = $agent->getAllowedTools();

        // Assert: null means all tools are allowed
        $this->assertNull($result);
    }

    /**
     * Test getAllowedTools returns specific tools when restricted.
     */
    public function test_get_allowed_tools_returns_specific_tools(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = [
            'tools_enabled' => true,
            'allowed_tools' => ['App\\Tools\\SearchTool', 'App\\Tools\\CalcTool'],
        ];

        // Act
        $result = $agent->getAllowedTools();

        // Assert: returns the specific allowed tools
        $this->assertSame(['App\\Tools\\SearchTool', 'App\\Tools\\CalcTool'], $result);
    }

    /**
     * Test getAllowedTools returns null when allowed_tools is empty array.
     *
     * empty($allowedTools) check treats [] as empty, returning null.
     */
    public function test_get_allowed_tools_returns_null_for_empty_list(): void
    {
        // Arrange
        $agent = new AiAgent;
        $agent->capabilities = [
            'tools_enabled' => true,
            'allowed_tools' => [],
        ];

        // Act
        $result = $agent->getAllowedTools();

        // Assert: empty list treated as unrestricted
        $this->assertNull($result);
    }

    /**
     * Test table name is explicitly set.
     */
    public function test_table_name_is_ai_agents(): void
    {
        // Arrange
        $agent = new AiAgent;

        // Assert
        $this->assertSame('ai_agents', $agent->getTable());
    }
}
