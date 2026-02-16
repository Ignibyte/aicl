<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\EntitySpec;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntitySpec value object methods.
 */
class EntitySpecTest extends TestCase
{
    public function test_wants_views_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->wantsViews());
    }

    public function test_wants_views_returns_true_when_option_set(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['views' => true],
        );

        $this->assertTrue($spec->wantsViews());
    }

    public function test_wants_views_returns_false_when_option_false(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['views' => false],
        );

        $this->assertFalse($spec->wantsViews());
    }

    public function test_wants_views_returns_false_for_string_true(): void
    {
        // The method uses strict === true comparison
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['views' => 'true'],
        );

        $this->assertFalse($spec->wantsViews());
    }

    public function test_wants_widgets_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->wantsWidgets());
    }

    public function test_wants_widgets_returns_true_when_set(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['widgets' => true],
        );

        $this->assertTrue($spec->wantsWidgets());
    }

    public function test_wants_notifications_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->wantsNotifications());
    }

    public function test_wants_pdf_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->wantsPdf());
    }

    public function test_wants_pdf_returns_true_when_set(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['pdf' => true],
        );

        $this->assertTrue($spec->wantsPdf());
    }

    public function test_wants_ai_context_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->wantsAiContext());
    }

    public function test_wants_ai_context_returns_true_with_option(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['ai-context' => true],
        );

        $this->assertTrue($spec->wantsAiContext());
    }

    public function test_wants_ai_context_returns_true_with_trait(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            traits: ['HasAiContext'],
        );

        $this->assertTrue($spec->wantsAiContext());
    }

    public function test_wants_filament_returns_true_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertTrue($spec->wantsFilament());
    }

    public function test_wants_filament_returns_false_when_disabled(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['filament' => false],
        );

        $this->assertFalse($spec->wantsFilament());
    }

    public function test_wants_api_returns_true_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertTrue($spec->wantsApi());
    }

    public function test_wants_api_returns_false_when_disabled(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            options: ['api' => false],
        );

        $this->assertFalse($spec->wantsApi());
    }

    public function test_has_state_machine_returns_false_when_empty(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->hasStateMachine());
    }

    public function test_has_state_machine_returns_true_with_states(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            states: ['draft', 'active', 'completed'],
        );

        $this->assertTrue($spec->hasStateMachine());
    }

    public function test_has_structured_widgets_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->hasStructuredWidgets());
    }

    public function test_has_structured_notifications_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->hasStructuredNotifications());
    }

    public function test_has_observer_rules_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->hasObserverRules());
    }

    public function test_has_report_layout_returns_false_by_default(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertFalse($spec->hasReportLayout());
    }

    public function test_to_fields_string_returns_empty_when_no_fields(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertEquals('', $spec->toFieldsString());
    }

    public function test_to_states_string_joins_states(): void
    {
        $spec = new EntitySpec(
            name: 'Test',
            description: 'Test entity',
            states: ['draft', 'active', 'completed'],
        );

        $this->assertEquals('draft,active,completed', $spec->toStatesString());
    }

    public function test_to_relationships_string_returns_empty_when_no_relationships(): void
    {
        $spec = new EntitySpec(name: 'Test', description: 'Test entity');

        $this->assertEquals('', $spec->toRelationshipsString());
    }
}
