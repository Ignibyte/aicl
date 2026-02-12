<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\SemanticCheck;
use Aicl\Rlm\SemanticCheckRegistry;
use PHPUnit\Framework\TestCase;

class SemanticCheckRegistryTest extends TestCase
{
    public function test_all_returns_eight_checks(): void
    {
        $checks = SemanticCheckRegistry::all();

        $this->assertCount(8, $checks);
        $this->assertContainsOnlyInstancesOf(SemanticCheck::class, $checks);
    }

    public function test_all_check_names_are_unique(): void
    {
        $checks = SemanticCheckRegistry::all();
        $names = array_map(fn (SemanticCheck $c): string => $c->name, $checks);

        $this->assertSame($names, array_unique($names));
    }

    public function test_all_checks_have_semantic_prefix(): void
    {
        foreach (SemanticCheckRegistry::all() as $check) {
            $this->assertStringStartsWith('semantic.', $check->name, "Check {$check->name} missing semantic. prefix");
        }
    }

    public function test_expected_check_names_exist(): void
    {
        $names = array_map(
            fn (SemanticCheck $c): string => $c->name,
            SemanticCheckRegistry::all(),
        );

        $expected = [
            'semantic.factory_types',
            'semantic.validation_nullability',
            'semantic.authorization_coverage',
            'semantic.resource_exposure',
            'semantic.test_coverage',
            'semantic.searchable_columns',
            'semantic.widget_queries',
            'semantic.state_transitions',
        ];

        foreach ($expected as $name) {
            $this->assertContains($name, $names, "Missing check: {$name}");
        }
    }

    public function test_error_severity_checks(): void
    {
        $errors = array_filter(
            SemanticCheckRegistry::all(),
            fn (SemanticCheck $c): bool => $c->isError(),
        );

        $errorNames = array_map(fn (SemanticCheck $c): string => $c->name, $errors);

        $this->assertContains('semantic.factory_types', $errorNames);
        $this->assertContains('semantic.authorization_coverage', $errorNames);
        $this->assertContains('semantic.resource_exposure', $errorNames);
        $this->assertContains('semantic.searchable_columns', $errorNames);
    }

    public function test_warning_severity_checks(): void
    {
        $warnings = array_filter(
            SemanticCheckRegistry::all(),
            fn (SemanticCheck $c): bool => $c->isWarning(),
        );

        $warningNames = array_map(fn (SemanticCheck $c): string => $c->name, $warnings);

        $this->assertContains('semantic.validation_nullability', $warningNames);
        $this->assertContains('semantic.test_coverage', $warningNames);
        $this->assertContains('semantic.widget_queries', $warningNames);
        $this->assertContains('semantic.state_transitions', $warningNames);
    }

    public function test_conditional_checks_have_applies_when(): void
    {
        $checks = SemanticCheckRegistry::all();

        $widgetCheck = $this->findCheck($checks, 'semantic.widget_queries');
        $this->assertSame('has_widgets', $widgetCheck->appliesWhen);

        $stateCheck = $this->findCheck($checks, 'semantic.state_transitions');
        $this->assertSame('has_states', $stateCheck->appliesWhen);
    }

    public function test_unconditional_checks_have_null_applies_when(): void
    {
        $checks = SemanticCheckRegistry::all();

        $unconditional = [
            'semantic.factory_types',
            'semantic.validation_nullability',
            'semantic.authorization_coverage',
            'semantic.resource_exposure',
            'semantic.test_coverage',
            'semantic.searchable_columns',
        ];

        foreach ($unconditional as $name) {
            $check = $this->findCheck($checks, $name);
            $this->assertNull($check->appliesWhen, "{$name} should have null appliesWhen");
        }
    }

    public function test_applicable_filters_by_context(): void
    {
        // No context — conditional checks excluded
        $applicable = SemanticCheckRegistry::applicable();
        $names = array_map(fn (SemanticCheck $c): string => $c->name, $applicable);

        $this->assertCount(6, $applicable);
        $this->assertNotContains('semantic.widget_queries', $names);
        $this->assertNotContains('semantic.state_transitions', $names);
    }

    public function test_applicable_includes_widgets_when_context_set(): void
    {
        $applicable = SemanticCheckRegistry::applicable(['has_widgets' => true]);
        $names = array_map(fn (SemanticCheck $c): string => $c->name, $applicable);

        $this->assertCount(7, $applicable);
        $this->assertContains('semantic.widget_queries', $names);
        $this->assertNotContains('semantic.state_transitions', $names);
    }

    public function test_applicable_includes_states_when_context_set(): void
    {
        $applicable = SemanticCheckRegistry::applicable(['has_states' => true]);
        $names = array_map(fn (SemanticCheck $c): string => $c->name, $applicable);

        $this->assertCount(7, $applicable);
        $this->assertContains('semantic.state_transitions', $names);
        $this->assertNotContains('semantic.widget_queries', $names);
    }

    public function test_applicable_includes_all_when_both_contexts_set(): void
    {
        $applicable = SemanticCheckRegistry::applicable([
            'has_widgets' => true,
            'has_states' => true,
        ]);

        $this->assertCount(8, $applicable);
    }

    public function test_all_checks_have_non_empty_prompts(): void
    {
        foreach (SemanticCheckRegistry::all() as $check) {
            $this->assertNotEmpty(trim($check->prompt), "Check {$check->name} has empty prompt");
        }
    }

    public function test_all_checks_have_targets(): void
    {
        foreach (SemanticCheckRegistry::all() as $check) {
            $this->assertNotEmpty($check->targets, "Check {$check->name} has no targets");
        }
    }

    public function test_total_weight_all_applicable(): void
    {
        $checks = SemanticCheckRegistry::applicable([
            'has_widgets' => true,
            'has_states' => true,
        ]);

        $totalWeight = array_sum(array_map(fn (SemanticCheck $c): float => $c->weight, $checks));

        $this->assertSame(13.5, $totalWeight);
    }

    public function test_total_weight_base_applicable(): void
    {
        $checks = SemanticCheckRegistry::applicable();
        $totalWeight = array_sum(array_map(fn (SemanticCheck $c): float => $c->weight, $checks));

        $this->assertSame(11.0, $totalWeight);
    }

    /**
     * @param  SemanticCheck[]  $checks
     */
    private function findCheck(array $checks, string $name): SemanticCheck
    {
        foreach ($checks as $check) {
            if ($check->name === $name) {
                return $check;
            }
        }

        $this->fail("Check not found: {$name}");
    }
}
