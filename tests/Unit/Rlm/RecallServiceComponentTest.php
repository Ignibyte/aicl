<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Components\ComponentRecommendation;
use Aicl\Components\ComponentRegistry;
use Aicl\Rlm\RecallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Tests for RecallService::getComponentRecommendations().
 */
class RecallServiceComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        \App\Models\User::factory()->create(['id' => 1]);
    }

    public function test_returns_empty_for_non_ui_agents(): void
    {
        $service = app(RecallService::class);

        $result = $service->getComponentRecommendations('tester', 4, ['fields' => ['name' => 'string']]);

        $this->assertEmpty($result);
    }

    public function test_returns_empty_for_non_ui_phases(): void
    {
        $service = app(RecallService::class);

        $result = $service->getComponentRecommendations('architect', 7, ['fields' => ['name' => 'string']]);

        $this->assertEmpty($result);
    }

    public function test_returns_empty_when_no_fields(): void
    {
        $service = app(RecallService::class);

        $result = $service->getComponentRecommendations('architect', 3, ['fields' => []]);

        $this->assertEmpty($result);
    }

    public function test_returns_empty_when_entity_context_null(): void
    {
        $service = app(RecallService::class);

        $result = $service->getComponentRecommendations('architect', 3, null);

        $this->assertEmpty($result);
    }

    public function test_returns_recommendations_for_architect_phase_3(): void
    {
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-status-badge',
            reason: 'Status field maps to status badge',
            suggestedProps: ['status' => '$record->status'],
            confidence: 0.9,
            alternative: 'Filament TextColumn::make("status")->badge()',
        );

        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([$recommendation]);
        $registry->shouldReceive('categories')
            ->andReturn(['metric', 'data', 'action', 'status']);
        $registry->shouldReceive('count')
            ->andReturn(33);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('architect', 3, ['fields' => ['status' => 'enum']]);

        $this->assertArrayHasKey('context_rules', $result);
        $this->assertArrayHasKey('field_recommendations', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('total_components', $result);
        $this->assertEquals(33, $result['total_components']);
    }

    public function test_returns_recommendations_for_designer_phase_3(): void
    {
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-stat-card',
            reason: 'Amount field maps to stat card',
            suggestedProps: ['value' => '$record->amount'],
            confidence: 0.8,
            alternative: null,
        );

        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([$recommendation]);
        $registry->shouldReceive('categories')
            ->andReturn(['metric']);
        $registry->shouldReceive('count')
            ->andReturn(33);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('designer', 3, ['fields' => ['amount' => 'float']]);

        $this->assertNotEmpty($result['field_recommendations']);
        $this->assertEquals('x-aicl-stat-card', $result['field_recommendations'][0]['component']);
        $this->assertEquals(0.8, $result['field_recommendations'][0]['confidence']);
    }

    public function test_returns_recommendations_for_rlm_phase_4(): void
    {
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-badge',
            reason: 'Type field maps to badge',
            suggestedProps: [],
            confidence: 0.7,
            alternative: null,
        );

        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([$recommendation]);
        $registry->shouldReceive('categories')
            ->andReturn(['status']);
        $registry->shouldReceive('count')
            ->andReturn(33);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('rlm', 4, ['fields' => ['type' => 'enum']]);

        $this->assertNotEmpty($result);
    }

    public function test_context_rules_contain_expected_keys(): void
    {
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-data-table',
            reason: 'Test',
            suggestedProps: [],
            confidence: 0.5,
            alternative: null,
        );

        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([$recommendation]);
        $registry->shouldReceive('categories')
            ->andReturn([]);
        $registry->shouldReceive('count')
            ->andReturn(33);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('architect', 5, ['fields' => ['name' => 'string']]);

        $this->assertArrayHasKey('blade', $result['context_rules']);
        $this->assertArrayHasKey('filament-form', $result['context_rules']);
        $this->assertArrayHasKey('filament-table', $result['context_rules']);
        $this->assertArrayHasKey('filament-widget', $result['context_rules']);
    }

    public function test_returns_empty_when_registry_throws(): void
    {
        // Unbind ComponentRegistry to simulate missing registration
        $this->app->bind(ComponentRegistry::class, function () {
            throw new \RuntimeException('Not available');
        });

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('architect', 3, ['fields' => ['name' => 'string']]);

        $this->assertEmpty($result);
    }

    public function test_returns_empty_when_no_recommendations_match(): void
    {
        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([]);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('architect', 3, ['fields' => ['name' => 'string']]);

        $this->assertEmpty($result);
    }

    public function test_field_recommendation_includes_alternative(): void
    {
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-status-badge',
            reason: 'Status enum field',
            suggestedProps: ['status' => 'active'],
            confidence: 0.95,
            alternative: 'TextColumn::make("status")->badge()',
        );

        $registry = Mockery::mock(ComponentRegistry::class);
        $registry->shouldReceive('recommendForEntity')
            ->andReturn([$recommendation]);
        $registry->shouldReceive('categories')
            ->andReturn([]);
        $registry->shouldReceive('count')
            ->andReturn(33);

        $this->app->instance(ComponentRegistry::class, $registry);

        $service = app(RecallService::class);
        $result = $service->getComponentRecommendations('architect', 3, ['fields' => ['status' => 'enum']]);

        $rec = $result['field_recommendations'][0];
        $this->assertEquals('TextColumn::make("status")->badge()', $rec['filament_alternative']);
        $this->assertEquals(['status' => 'active'], $rec['suggested_props']);
        $this->assertEquals('Status enum field', $rec['reason']);
    }

    public function test_recall_includes_component_recommendations_key(): void
    {
        $ks = app(\Aicl\Rlm\KnowledgeService::class);
        $ks->resetAvailabilityCache();

        $result = $ks->recall('architect', 3);

        $this->assertArrayHasKey('component_recommendations', $result);
    }
}
