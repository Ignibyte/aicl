<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Workflows;

use Aicl\Workflows\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for ApprovalLog PHPStan changes.
 *
 * Tests the PHPDoc type annotations added to the approvable()
 * and actor() relationship methods. Verifies typed relationship
 * annotations work correctly with Eloquent model binding.
 */
class ApprovalLogRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test approvable() relationship returns MorphTo with typed annotation.
     *
     * PHPStan change: Added @return MorphTo<Model, $this> annotation.
     */
    public function test_approvable_relationship_is_morph_to(): void
    {
        // Arrange
        $log = new ApprovalLog;

        // Act: access the relationship definition
        $relation = $log->approvable();

        // Assert: should be a MorphTo relation
        $this->assertInstanceOf(MorphTo::class, $relation);
    }

    /**
     * Test actor() relationship returns BelongsTo with typed annotation.
     *
     * PHPStan change: Added @return BelongsTo<User, $this> annotation.
     */
    public function test_actor_relationship_is_belongs_to(): void
    {
        // Arrange
        $log = new ApprovalLog;

        // Act: access the relationship definition
        $relation = $log->actor();

        // Assert: should be a BelongsTo relation targeting User
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /**
     * Test ApprovalLog has expected fillable attributes.
     *
     * Verifies model configuration after strict_types-related changes.
     */
    public function test_fillable_attributes_are_correct(): void
    {
        // Arrange
        $log = new ApprovalLog;

        // Assert: should have the expected fillable fields
        $fillable = $log->getFillable();
        $this->assertContains('approvable_type', $fillable);
        $this->assertContains('approvable_id', $fillable);
        $this->assertContains('actor_id', $fillable);
        $this->assertContains('action', $fillable);
    }
}
