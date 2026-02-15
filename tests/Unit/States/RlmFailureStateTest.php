<?php

namespace Aicl\Tests\Unit\States;

use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Deprecated;
use Aicl\States\RlmFailure\Investigating;
use Aicl\States\RlmFailure\Reported;
use Aicl\States\RlmFailure\Resolved;
use Aicl\States\RlmFailure\WontFix;
use Aicl\States\RlmFailureState;
use ReflectionClass;
use Tests\TestCase;

class RlmFailureStateTest extends TestCase
{
    public function test_all_states_extend_base_class(): void
    {
        $states = [
            Reported::class,
            Confirmed::class,
            Investigating::class,
            Resolved::class,
            WontFix::class,
            Deprecated::class,
        ];

        foreach ($states as $stateClass) {
            $this->assertTrue(
                is_subclass_of($stateClass, RlmFailureState::class),
                "{$stateClass} should extend RlmFailureState"
            );
        }
    }

    public function test_reported_state_properties(): void
    {
        $state = new Reported($this->createMockModel());

        $this->assertSame('Reported', $state->label());
        $this->assertSame('gray', $state->color());
        $this->assertSame('heroicon-o-pencil-square', $state->icon());
    }

    public function test_confirmed_state_properties(): void
    {
        $state = new Confirmed($this->createMockModel());

        $this->assertSame('Confirmed', $state->label());
        $this->assertSame('success', $state->color());
        $this->assertSame('heroicon-o-play', $state->icon());
    }

    public function test_investigating_state_properties(): void
    {
        $state = new Investigating($this->createMockModel());

        $this->assertSame('Investigating', $state->label());
        $this->assertSame('warning', $state->color());
        $this->assertSame('heroicon-o-pause', $state->icon());
    }

    public function test_resolved_state_properties(): void
    {
        $state = new Resolved($this->createMockModel());

        $this->assertSame('Resolved', $state->label());
        $this->assertSame('info', $state->color());
        $this->assertSame('heroicon-o-check-circle', $state->icon());
    }

    public function test_wont_fix_state_properties(): void
    {
        $state = new WontFix($this->createMockModel());

        $this->assertSame('Wont Fix', $state->label());
        $this->assertSame('danger', $state->color());
        $this->assertSame('heroicon-o-archive-box', $state->icon());
    }

    public function test_deprecated_state_properties(): void
    {
        $state = new Deprecated($this->createMockModel());

        $this->assertSame('Deprecated', $state->label());
        $this->assertSame('gray', $state->color());
        $this->assertSame('heroicon-o-pencil-square', $state->icon());
    }

    public function test_base_state_is_abstract(): void
    {
        $reflection = new ReflectionClass(RlmFailureState::class);

        $this->assertTrue($reflection->isAbstract(), 'RlmFailureState should be abstract');
    }

    public function test_state_count(): void
    {
        $config = RlmFailureState::config();
        $reflection = new ReflectionClass($config);

        $registeredProperty = $reflection->getProperty('registeredStates');
        $registeredProperty->setAccessible(true);
        $registeredStates = $registeredProperty->getValue($config);

        $this->assertCount(6, $registeredStates);
    }

    /**
     * Create a mock model instance for state instantiation.
     */
    private function createMockModel(): \Illuminate\Database\Eloquent\Model
    {
        return new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'rlm_failures';
        };
    }
}
