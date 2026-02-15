<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\RlmFailure;
use Aicl\States\RlmFailure\Deprecated;
use Aicl\States\RlmFailure\WontFix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A-006: Exercise the wontFix() and deprecated() factory states.
 */
class RlmFailureFactoryStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        User::factory()->create(['id' => 1]);
    }

    public function test_wont_fix_factory_state_creates_record_with_correct_status(): void
    {
        $failure = RlmFailure::factory()->wontFix()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
        $this->assertInstanceOf(WontFix::class, $failure->status);
    }

    public function test_wont_fix_state_has_expected_morph_class(): void
    {
        $failure = RlmFailure::factory()->wontFix()->create(['owner_id' => 1]);

        $this->assertSame(WontFix::getMorphClass(), $failure->status->getValue());
    }

    public function test_deprecated_factory_state_creates_record_with_correct_status(): void
    {
        $failure = RlmFailure::factory()->deprecated()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $failure->id]);
        $this->assertInstanceOf(Deprecated::class, $failure->status);
    }

    public function test_deprecated_state_has_expected_morph_class(): void
    {
        $failure = RlmFailure::factory()->deprecated()->create(['owner_id' => 1]);

        $this->assertSame(Deprecated::getMorphClass(), $failure->status->getValue());
    }

    public function test_wont_fix_and_deprecated_are_distinct_states(): void
    {
        $wontFix = RlmFailure::factory()->wontFix()->create(['owner_id' => 1]);
        $deprecated = RlmFailure::factory()->deprecated()->create(['owner_id' => 1]);

        $this->assertNotSame(
            $wontFix->status->getValue(),
            $deprecated->status->getValue(),
        );
    }

    public function test_wont_fix_state_can_be_combined_with_other_factory_states(): void
    {
        $failure = RlmFailure::factory()
            ->wontFix()
            ->scaffoldingFixed()
            ->create(['owner_id' => 1]);

        $this->assertInstanceOf(WontFix::class, $failure->status);
        $this->assertTrue($failure->scaffolding_fixed);
    }

    public function test_deprecated_state_can_be_combined_with_other_factory_states(): void
    {
        $failure = RlmFailure::factory()
            ->deprecated()
            ->promoted()
            ->create(['owner_id' => 1]);

        $this->assertInstanceOf(Deprecated::class, $failure->status);
        $this->assertTrue($failure->promoted_to_base);
    }
}
