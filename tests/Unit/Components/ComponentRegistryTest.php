<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentDefinition;
use Aicl\Components\ComponentRecommendation;
use Aicl\Components\ComponentRegistry;
use Tests\TestCase;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ComponentRegistry::class);
    }

    // ─── all() ─────────────────────────────────────────────────

    public function test_all_returns_collection_of_definitions(): void
    {
        $all = $this->registry->all();
        $this->assertGreaterThanOrEqual(33, $all->count());

        foreach ($all as $component) {
            $this->assertInstanceOf(ComponentDefinition::class, $component);
        }
    }

    // ─── get() ─────────────────────────────────────────────────

    public function test_get_returns_component_by_short_tag(): void
    {
        $component = $this->registry->get('stat-card');
        $this->assertNotNull($component);
        $this->assertEquals('Stat Card', $component->name);
    }

    public function test_get_accepts_full_tag_with_prefix(): void
    {
        $component = $this->registry->get('x-aicl-stat-card');
        $this->assertNotNull($component);
        $this->assertEquals('stat-card', $component->shortTag());
    }

    public function test_get_returns_null_for_unknown_tag(): void
    {
        $this->assertNull($this->registry->get('nonexistent-component'));
    }

    // ─── forCategory() ─────────────────────────────────────────

    public function test_for_category_returns_matching_components(): void
    {
        $metrics = $this->registry->forCategory('metric');
        $this->assertGreaterThanOrEqual(3, $metrics->count());

        foreach ($metrics as $component) {
            $this->assertEquals('metric', $component->category);
        }
    }

    public function test_for_category_returns_empty_for_unknown(): void
    {
        $result = $this->registry->forCategory('nonexistent-category');
        $this->assertTrue($result->isEmpty());
    }

    // ─── forContext() ──────────────────────────────────────────

    public function test_for_context_filters_by_supported_context(): void
    {
        $bladeComponents = $this->registry->forContext('blade');
        $this->assertGreaterThan(0, $bladeComponents->count());

        foreach ($bladeComponents as $component) {
            $this->assertTrue($component->supportsContext('blade'));
        }
    }

    // ─── recommend() ───────────────────────────────────────────

    public function test_recommend_returns_recommendation_for_status_enum(): void
    {
        $rec = $this->registry->recommend('enum', 'blade', 'status');
        $this->assertNotNull($rec);
        $this->assertInstanceOf(ComponentRecommendation::class, $rec);
        $this->assertEquals('x-aicl-status-badge', $rec->tag);
    }

    public function test_recommend_returns_null_for_text_field(): void
    {
        $rec = $this->registry->recommend('text', 'blade', 'description');
        $this->assertNull($rec);
    }

    // ─── recommendForEntity() ──────────────────────────────────

    public function test_recommend_for_entity_with_mixed_fields(): void
    {
        $fields = ['status' => 'enum', 'budget' => 'float', 'name' => 'string'];
        $recs = $this->registry->recommendForEntity($fields);

        $this->assertIsArray($recs);
        $this->assertGreaterThanOrEqual(2, count($recs));
    }

    public function test_recommend_for_entity_accepts_colon_format(): void
    {
        $fields = ['status:enum', 'budget:float', 'name:string'];
        $recs = $this->registry->recommendForEntity($fields);

        $this->assertIsArray($recs);
        $this->assertGreaterThanOrEqual(2, count($recs));
    }

    // ─── schema() ──────────────────────────────────────────────

    public function test_schema_returns_props_array(): void
    {
        $schema = $this->registry->schema('stat-card');
        $this->assertNotNull($schema);
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('label', $schema);
    }

    public function test_schema_returns_null_for_unknown(): void
    {
        $this->assertNull($this->registry->schema('nonexistent'));
    }

    // ─── validateProps() ───────────────────────────────────────

    public function test_validate_props_passes_with_required_props(): void
    {
        $component = $this->registry->get('stat-card');
        $this->assertNotNull($component);

        $required = $component->requiredProps();
        $props = array_fill_keys($required, 'test-value');

        $result = $this->registry->validateProps('stat-card', $props);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_props_fails_for_missing_required(): void
    {
        $component = $this->registry->get('stat-card');
        $this->assertNotNull($component);

        $required = $component->requiredProps();
        if (empty($required)) {
            $this->markTestSkipped('stat-card has no required props');
        }

        $result = $this->registry->validateProps('stat-card', []);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_props_fails_for_unknown_component(): void
    {
        $result = $this->registry->validateProps('nonexistent', []);
        $this->assertFalse($result['valid']);
    }

    // ─── composableChildren() ──────────────────────────────────

    public function test_composable_children_returns_valid_children(): void
    {
        $children = $this->registry->composableChildren('stats-row');
        $this->assertGreaterThanOrEqual(3, $children->count());

        foreach ($children as $child) {
            $this->assertContains('stats-row', $child->composableIn);
        }
    }

    public function test_composable_children_accepts_full_tag(): void
    {
        $children = $this->registry->composableChildren('x-aicl-stats-row');
        $this->assertGreaterThanOrEqual(3, $children->count());
    }

    // ─── count() ───────────────────────────────────────────────

    public function test_count_matches_all_collection(): void
    {
        $this->assertEquals($this->registry->all()->count(), $this->registry->count());
    }

    // ─── categories() ──────────────────────────────────────────

    public function test_categories_returns_unique_sorted_list(): void
    {
        $categories = $this->registry->categories();
        $this->assertIsArray($categories);
        $this->assertGreaterThanOrEqual(5, count($categories));
        $this->assertContains('metric', $categories);
        $this->assertContains('layout', $categories);

        // Verify sorted
        $sorted = $categories;
        sort($sorted);
        $this->assertEquals($sorted, $categories);
    }

    // ─── register() ────────────────────────────────────────────

    public function test_register_adds_definitions_directly(): void
    {
        $registry = new ComponentRegistry(new \Aicl\Components\ComponentDiscoveryService);
        $definition = new ComponentDefinition(
            name: 'Test Component',
            tag: 'x-aicl-test',
            class: 'Aicl\\View\\Components\\Test',
            template: 'test.blade.php',
            jsModule: null,
            category: 'testing',
            status: 'stable',
            description: 'Test only',
            context: ['blade'],
            notFor: [],
            props: [],
            slots: [],
            variants: [],
            composableIn: [],
            decisionRule: 'Never use — test only',
            fieldSignals: [],
            filamentEquivalent: null,
            source: 'test',
        );

        $registry->register($definition);
        $this->assertEquals(1, $registry->count());
        $this->assertNotNull($registry->get('test'));
    }

    // ─── Cache ─────────────────────────────────────────────────

    public function test_write_cache_creates_file(): void
    {
        $path = $this->registry->writeCache();
        $this->assertFileExists($path);

        // Cleanup
        $this->registry->clearCache();
    }

    public function test_clear_cache_removes_file(): void
    {
        $this->registry->writeCache();
        $this->assertTrue($this->registry->isCached());

        $this->registry->clearCache();
        $this->assertFalse($this->registry->isCached());
    }
}
