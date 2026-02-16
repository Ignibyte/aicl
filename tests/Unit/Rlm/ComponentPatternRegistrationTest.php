<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\PatternRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests the 10 component patterns (C01-C10) and 8 view patterns (V01-V08)
 * added in Sprint O.
 */
class ComponentPatternRegistrationTest extends TestCase
{
    // ─── Pattern Registration ──────────────────────────────────

    public function test_component_patterns_are_registered(): void
    {
        $patterns = PatternRegistry::componentPatterns();
        $this->assertCount(10, $patterns);
    }

    public function test_view_patterns_are_registered(): void
    {
        $patterns = PatternRegistry::viewPatterns();
        $this->assertCount(8, $patterns);
    }

    public function test_all_patterns_include_component_and_view(): void
    {
        $all = PatternRegistry::all();
        $componentPatterns = array_filter($all, fn (EntityPattern $p) => str_starts_with($p->name, 'component.'));
        $viewPatterns = array_filter($all, fn (EntityPattern $p) => str_starts_with($p->name, 'view.'));

        $this->assertCount(10, $componentPatterns);
        $this->assertCount(8, $viewPatterns);
    }

    public function test_total_pattern_count_is_64(): void
    {
        $all = PatternRegistry::all();
        $this->assertCount(64, $all);
    }

    public function test_total_with_registration_patterns_is_68(): void
    {
        $all = PatternRegistry::all('TestEntity');
        $this->assertCount(68, $all);
    }

    // ─── Component Patterns (C01-C10) ──────────────────────────

    #[DataProvider('componentPatternProvider')]
    public function test_component_pattern_has_valid_structure(string $expectedName, string $expectedTarget): void
    {
        $patterns = PatternRegistry::componentPatterns();
        $match = array_values(array_filter($patterns, fn (EntityPattern $p) => $p->name === $expectedName));

        $this->assertCount(1, $match, "Pattern '{$expectedName}' not found");
        $pattern = $match[0];
        $this->assertEquals($expectedTarget, $pattern->target);
        $this->assertNotEmpty($pattern->description);
        $this->assertNotEmpty($pattern->check);
        $this->assertGreaterThan(0, $pattern->weight);
    }

    public static function componentPatternProvider(): array
    {
        return [
            'C01' => ['component.uses_aicl_components', 'blade_view'],
            'C02' => ['component.status_uses_badge', 'blade_view'],
            'C03' => ['component.metrics_use_cards', 'blade_widget'],
            'C04' => ['component.statsrow_children', 'blade_widget'],
            'C05' => ['component.collection_uses_table', 'blade_view'],
            'C06' => ['component.empty_state_has_cta', 'blade_view'],
            'C07' => ['component.dark_mode_support', 'blade_view'],
            'C08' => ['component.responsive_grid', 'blade_view'],
            'C09' => ['component.layout_structure', 'blade_view'],
            'C10' => ['component.widget_uses_components', 'blade_widget'],
        ];
    }

    // ─── View Patterns (V01-V08) ───────────────────────────────

    #[DataProvider('viewPatternProvider')]
    public function test_view_pattern_has_valid_structure(string $expectedName, string $expectedTarget, string $expectedSeverity): void
    {
        $patterns = PatternRegistry::viewPatterns();
        $match = array_values(array_filter($patterns, fn (EntityPattern $p) => $p->name === $expectedName));

        $this->assertCount(1, $match, "Pattern '{$expectedName}' not found");
        $pattern = $match[0];
        $this->assertEquals($expectedTarget, $pattern->target);
        $this->assertEquals($expectedSeverity, $pattern->severity);
        $this->assertNotEmpty($pattern->description);
        $this->assertNotEmpty($pattern->check);
    }

    public static function viewPatternProvider(): array
    {
        return [
            'V01 blade_structure' => ['view.blade_structure', 'blade_index', 'error'],
            'V02 alpine_component' => ['view.alpine_component', 'blade_index', 'warning'],
            'V03 component_composition' => ['view.component_composition', 'blade_show', 'warning'],
            'V04 tailwind_tokens' => ['view.tailwind_tokens', 'blade_show', 'warning'],
            'V05 accessibility' => ['view.accessibility', 'blade_index', 'warning'],
            'V06 echo_binding' => ['view.echo_binding', 'blade_index', 'info'],
            'V07 controller_pair' => ['view.controller_pair', 'view_controller', 'error'],
            'V08 responsive_layout' => ['view.responsive_layout', 'blade_index', 'warning'],
        ];
    }

    // ─── Pattern name uniqueness ───────────────────────────────

    public function test_all_pattern_names_are_unique(): void
    {
        $patterns = PatternRegistry::all('TestEntity');
        $names = array_map(fn (EntityPattern $p) => $p->name, $patterns);
        $this->assertEquals($names, array_unique($names), 'All pattern names should be unique');
    }

    // ─── Pattern regex validity ────────────────────────────────

    public function test_all_pattern_checks_are_valid_regex(): void
    {
        $patterns = PatternRegistry::all('TestEntity');
        foreach ($patterns as $pattern) {
            $result = @preg_match('/'.$pattern->check.'/', '');
            $this->assertNotFalse($result, "Pattern '{$pattern->name}' has invalid regex: {$pattern->check}");
        }
    }
}
