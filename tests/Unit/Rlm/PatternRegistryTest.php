<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\PatternRegistry;
use PHPUnit\Framework\TestCase;

class PatternRegistryTest extends TestCase
{
    public function test_all_returns_array_of_entity_patterns(): void
    {
        $patterns = PatternRegistry::all();

        $this->assertIsArray($patterns);
        $this->assertNotEmpty($patterns);

        foreach ($patterns as $pattern) {
            $this->assertInstanceOf(EntityPattern::class, $pattern);
        }
    }

    public function test_all_contains_patterns_from_all_categories(): void
    {
        $patterns = PatternRegistry::all();
        $targets = array_unique(array_map(fn (EntityPattern $p) => $p->target, $patterns));

        $this->assertContains('model', $targets);
        $this->assertContains('migration', $targets);
        $this->assertContains('factory', $targets);
        $this->assertContains('policy', $targets);
        $this->assertContains('observer', $targets);
        $this->assertContains('filament', $targets);
        $this->assertContains('form', $targets);
        $this->assertContains('infolist', $targets);
        $this->assertContains('test', $targets);
    }

    public function test_model_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::modelPatterns();

        $this->assertCount(14, $patterns);
    }

    public function test_migration_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::migrationPatterns();

        $this->assertCount(5, $patterns);
    }

    public function test_factory_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::factoryPatterns();

        $this->assertCount(5, $patterns);
    }

    public function test_policy_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::policyPatterns();

        $this->assertCount(5, $patterns);
    }

    public function test_observer_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::observerPatterns();

        $this->assertCount(3, $patterns);
    }

    public function test_filament_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::filamentPatterns();

        $this->assertCount(12, $patterns);
    }

    public function test_test_patterns_has_expected_count(): void
    {
        $patterns = PatternRegistry::testPatterns();

        $this->assertCount(4, $patterns);
    }

    public function test_all_returns_total_of_all_category_patterns(): void
    {
        $total = count(PatternRegistry::modelPatterns())
            + count(PatternRegistry::migrationPatterns())
            + count(PatternRegistry::factoryPatterns())
            + count(PatternRegistry::policyPatterns())
            + count(PatternRegistry::observerPatterns())
            + count(PatternRegistry::filamentPatterns())
            + count(PatternRegistry::testPatterns())
            + count(PatternRegistry::specPatterns())
            + count(PatternRegistry::componentPatterns())
            + count(PatternRegistry::viewPatterns());

        $this->assertCount($total, PatternRegistry::all());
    }

    public function test_every_pattern_has_unique_name(): void
    {
        $patterns = PatternRegistry::all();
        $names = array_map(fn (EntityPattern $p) => $p->name, $patterns);

        $this->assertEquals(count($names), count(array_unique($names)));
    }

    public function test_every_pattern_has_non_empty_check(): void
    {
        foreach (PatternRegistry::all() as $pattern) {
            $this->assertNotEmpty($pattern->check, "Pattern {$pattern->name} has empty check");
        }
    }

    public function test_every_pattern_has_positive_weight(): void
    {
        foreach (PatternRegistry::all() as $pattern) {
            $this->assertGreaterThan(0, $pattern->weight, "Pattern {$pattern->name} has non-positive weight");
        }
    }

    public function test_every_pattern_severity_is_error_or_warning(): void
    {
        foreach (PatternRegistry::all() as $pattern) {
            $this->assertContains(
                $pattern->severity,
                ['error', 'warning', 'info'],
                "Pattern {$pattern->name} has invalid severity: {$pattern->severity}",
            );
        }
    }
}
