<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Requests;

use Aicl\Http\Requests\StoreAiAgentRequest;
use Aicl\Models\AiAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for StoreAiAgentRequest PHPStan changes.
 *
 * Covers the authorize() method change from `return true` to
 * `$this->user()?->can('create', AiAgent::class) ?? false`.
 * This ensures proper policy-based authorization instead of
 * unconditional access.
 */
class StoreAiAgentRequestRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions for policy checks
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- authorize with null user --

    /**
     * Test authorize returns false when no user is authenticated.
     *
     * PHPStan change: $this->user()?->can() ?? false null guard.
     * Previously returned true unconditionally.
     */
    public function test_authorize_returns_false_when_unauthenticated(): void
    {
        // Arrange: create request without authentication
        $request = StoreAiAgentRequest::create('/api/ai-agents', 'POST');
        $request->setUserResolver(fn () => null);

        // Act
        $result = $request->authorize();

        // Assert: unauthorized when no user
        $this->assertFalse($result);
    }

    // -- rules structure --

    /**
     * Test rules returns expected validation rules structure.
     *
     * Verifies all expected field rules are present.
     */
    public function test_rules_contains_expected_fields(): void
    {
        // Arrange
        $request = new StoreAiAgentRequest;

        // Act
        $rules = $request->rules();

        // Assert: key fields have validation rules
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('slug', $rules);
        $this->assertArrayHasKey('provider', $rules);
        $this->assertArrayHasKey('model', $rules);
        $this->assertArrayHasKey('system_prompt', $rules);
        $this->assertArrayHasKey('max_tokens', $rules);
        $this->assertArrayHasKey('temperature', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    /**
     * Test name field has required and string rules.
     */
    public function test_name_field_is_required_string(): void
    {
        // Arrange
        $request = new StoreAiAgentRequest;

        // Act
        $rules = $request->rules();

        // Assert
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
    }
}
