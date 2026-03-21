<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentRecommendation;
use Aicl\Components\FieldSignalEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FieldSignalEngineTest extends TestCase
{
    private FieldSignalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FieldSignalEngine;
    }

    // ─── Single-field matching ─────────────────────────────────

    public function test_status_enum_returns_status_badge(): void
    {
        $result = $this->engine->match('status', 'enum');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-status-badge', $result->tag);
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function test_status_state_returns_status_badge(): void
    {
        $result = $this->engine->match('status', 'state');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-status-badge', $result->tag);
    }

    public function test_progress_integer_returns_progress_card(): void
    {
        $result = $this->engine->match('progress', 'integer');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-progress-card', $result->tag);
    }

    public function test_progress_float_returns_progress_card(): void
    {
        $result = $this->engine->match('progress', 'float');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-progress-card', $result->tag);
    }

    public function test_count_field_returns_stat_card(): void
    {
        $result = $this->engine->match('task_count', 'integer');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-stat-card', $result->tag);
    }

    public function test_total_field_returns_stat_card(): void
    {
        $result = $this->engine->match('total_items', 'integer');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-stat-card', $result->tag);
    }

    #[DataProvider('monetaryFieldProvider')]
    public function test_monetary_fields_return_stat_card(string $name, string $type): void
    {
        $result = $this->engine->match($name, $type);
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-stat-card', $result->tag);
    }

    /** @phpstan-ignore-next-line */
    public static function monetaryFieldProvider(): array
    {
        return [
            'budget float' => ['budget', 'float'],
            'amount decimal' => ['amount', 'decimal'],
            'price integer' => ['price', 'integer'],
            'cost float' => ['cost', 'float'],
            'total float' => ['total', 'float'],
            'revenue float' => ['revenue', 'float'],
        ];
    }

    public function test_boolean_flag_returns_status_badge(): void
    {
        $result = $this->engine->match('is_active', 'boolean');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-status-badge', $result->tag);
    }

    public function test_datetime_field_returns_trend_card(): void
    {
        $result = $this->engine->match('published_at', 'datetime');
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-trend-card', $result->tag);
    }

    public function test_unrecognized_field_returns_null(): void
    {
        $result = $this->engine->match('description', 'text');
        $this->assertNull($result);
    }

    public function test_string_field_returns_null(): void
    {
        $result = $this->engine->match('name', 'string');
        $this->assertNull($result);
    }

    // ─── Multi-field matching ──────────────────────────────────

    public function test_datetime_range_pair_returns_data_table(): void
    {
        $allFields = [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'name' => 'string',
        ];

        $result = $this->engine->match('starts_at', 'datetime', 'blade', $allFields);
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-data-table', $result->tag);
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function test_target_actual_pair_returns_kpi_card(): void
    {
        $allFields = [
            'budget' => 'float',
            'spent' => 'float',
            'name' => 'string',
        ];

        $result = $this->engine->match('budget', 'float', 'blade', $allFields);
        $this->assertNotNull($result);
        $this->assertEquals('x-aicl-kpi-card', $result->tag);
    }

    public function test_target_without_actual_falls_through_to_monetary(): void
    {
        $allFields = [
            'budget' => 'float',
            'name' => 'string',
        ];

        $result = $this->engine->match('budget', 'float', 'blade', $allFields);
        $this->assertNotNull($result);
        // Without 'spent' pair, budget falls to monetary → stat-card
        $this->assertEquals('x-aicl-stat-card', $result->tag);
    }

    // ─── Entity-level recommendations ──────────────────────────

    public function test_recommend_for_entity_returns_multiple(): void
    {
        $fields = [
            'status' => 'enum',
            'budget' => 'float',
            'name' => 'string',
        ];

        $results = $this->engine->recommendForEntity($fields);
        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_recommend_for_entity_skips_duplicates(): void
    {
        $fields = [
            'status' => 'enum',
        ];

        $results = $this->engine->recommendForEntity($fields);
        $tags = array_map(fn (ComponentRecommendation $r) => $r->tag, $results);
        $this->assertEquals($tags, array_unique($tags));
    }

    public function test_recommend_for_entity_with_empty_fields(): void
    {
        $results = $this->engine->recommendForEntity([]);
        $this->assertCount(0, $results);
    }

    // ─── Context awareness ─────────────────────────────────────

    public function test_filament_context_provides_alternatives(): void
    {
        $result = $this->engine->match('status', 'enum', 'filament');
        $this->assertNotNull($result);
        // Should still recommend the component but include alternative
        $this->assertNotNull($result->alternative);
    }

    // ─── ComponentRecommendation VO ────────────────────────────

    public function test_recommendation_has_required_fields(): void
    {
        $result = $this->engine->match('status', 'enum');
        $this->assertNotNull($result);
        $this->assertInstanceOf(ComponentRecommendation::class, $result);
    }
}
