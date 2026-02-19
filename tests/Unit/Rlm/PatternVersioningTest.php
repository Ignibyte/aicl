<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\PatternRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternVersioningTest extends TestCase
{
    use RefreshDatabase;

    // ─── D.2: EntityPattern version fields ──────────────────────

    public function test_entity_pattern_defaults_to_v1(): void
    {
        $pattern = new EntityPattern(
            name: 'test.pattern',
            description: 'Test',
            target: 'model',
            check: 'test',
        );

        $this->assertSame('v1', $pattern->introducedIn);
        $this->assertNull($pattern->removedIn);
    }

    public function test_entity_pattern_is_active_in_version(): void
    {
        $pattern = new EntityPattern(
            name: 'test.pattern',
            description: 'Test',
            target: 'model',
            check: 'test',
            introducedIn: 'v1',
            removedIn: null,
        );

        $this->assertTrue($pattern->isActiveInVersion('v1'));
        $this->assertTrue($pattern->isActiveInVersion('v2'));
        $this->assertTrue($pattern->isActiveInVersion('v99'));
    }

    public function test_entity_pattern_respects_introduced_in(): void
    {
        $pattern = new EntityPattern(
            name: 'test.pattern',
            description: 'Test',
            target: 'model',
            check: 'test',
            introducedIn: 'v2',
        );

        $this->assertFalse($pattern->isActiveInVersion('v1'));
        $this->assertTrue($pattern->isActiveInVersion('v2'));
        $this->assertTrue($pattern->isActiveInVersion('v3'));
    }

    public function test_entity_pattern_respects_removed_in(): void
    {
        $pattern = new EntityPattern(
            name: 'test.pattern',
            description: 'Test',
            target: 'model',
            check: 'test',
            introducedIn: 'v1',
            removedIn: 'v3',
        );

        $this->assertTrue($pattern->isActiveInVersion('v1'));
        $this->assertTrue($pattern->isActiveInVersion('v2'));
        $this->assertFalse($pattern->isActiveInVersion('v3'));
        $this->assertFalse($pattern->isActiveInVersion('v4'));
    }

    // ─── D.3: PatternRegistry version support ───────────────────

    public function test_pattern_registry_has_version_constant(): void
    {
        $this->assertNotEmpty(PatternRegistry::VERSION);
        $this->assertSame(PatternRegistry::VERSION, PatternRegistry::currentVersion());
    }

    public function test_get_pattern_set_returns_all_for_current_version(): void
    {
        $all = PatternRegistry::all();
        $versioned = PatternRegistry::getPatternSet(PatternRegistry::VERSION);

        // All existing patterns are v1, so both should return same count
        $this->assertCount(count($all), $versioned);
    }

    public function test_get_pattern_set_filters_by_version(): void
    {
        // Since all patterns default to v1, filtering by v0 should return nothing
        // (v0 < v1, so no patterns are active in v0)
        $patterns = PatternRegistry::getPatternSet('v0');

        $this->assertEmpty($patterns);
    }

    // ─── D.4: EntityValidator version-aware ─────────────────────

    public function test_validator_without_version_has_warning(): void
    {
        $validator = new EntityValidator('TestEntity');
        $validator->validate();

        $this->assertTrue($validator->hasVersionWarning());
    }

    public function test_validator_with_version_has_no_warning(): void
    {
        $validator = new EntityValidator('TestEntity', PatternRegistry::VERSION);
        $validator->validate();

        $this->assertFalse($validator->hasVersionWarning());
    }

    public function test_validator_reports_pattern_version(): void
    {
        $validator = new EntityValidator('TestEntity', 'v2');
        $this->assertSame('v2', $validator->patternVersion());

        $unpinned = new EntityValidator('TestEntity');
        $this->assertSame(PatternRegistry::currentVersion(), $unpinned->patternVersion());
    }
}
