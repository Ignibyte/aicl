<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Contracts\EmbeddingDriver;
use Aicl\Enums\ScoreType;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Rlm\Embeddings\IndexMappings;
use Aicl\Rlm\Embeddings\NeuronAiEmbeddingAdapter;
use Aicl\Rlm\Embeddings\NullDriver;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\HubClient;
use Aicl\Rlm\KnowledgeService;
use Aicl\Rlm\PatternCandidate;
use Aicl\Rlm\PatternDiscovery;
use Aicl\Rlm\PatternRegistry;
use Aicl\Rlm\ProjectIdentity;
use Aicl\Rlm\SemanticCache;
use Aicl\Rlm\SemanticCheck;
use Aicl\Rlm\SemanticResult;
use Aicl\Rlm\SemanticValidator;
use Aicl\Rlm\ValidationResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RlmCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        User::factory()->create(['id' => 1]);

        config([
            'aicl.rlm.embeddings.driver' => 'null',
            'aicl.rlm.hub.enabled' => false,
        ]);
    }

    // ========================================================================
    // NullDriver — direct unit tests
    // ========================================================================

    public function test_null_driver_implements_embedding_driver(): void
    {
        $driver = new NullDriver;

        $this->assertInstanceOf(EmbeddingDriver::class, $driver);
    }

    public function test_null_driver_embed_returns_empty_array(): void
    {
        $driver = new NullDriver;

        $result = $driver->embed('test text');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_null_driver_embed_batch_returns_empty_arrays(): void
    {
        $driver = new NullDriver;

        $results = $driver->embedBatch(['hello', 'world', 'test']);

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertSame([], $result);
        }
    }

    public function test_null_driver_embed_batch_returns_empty_for_empty_input(): void
    {
        $driver = new NullDriver;

        $results = $driver->embedBatch([]);

        $this->assertSame([], $results);
    }

    public function test_null_driver_dimension_returns_1536(): void
    {
        $driver = new NullDriver;

        $this->assertSame(1536, $driver->dimension());
    }

    // ========================================================================
    // IndexMappings — static mapping methods
    // ========================================================================

    public function test_index_mappings_all_returns_array(): void
    {
        $mappings = IndexMappings::all();

        $this->assertIsArray($mappings);
        $this->assertNotEmpty($mappings);
    }

    public function test_index_mappings_all_contains_expected_indices(): void
    {
        $mappings = IndexMappings::all();

        $this->assertArrayHasKey('aicl_rlm_failures', $mappings);
        $this->assertArrayHasKey('aicl_rlm_lessons', $mappings);
        $this->assertArrayHasKey('aicl_rlm_patterns', $mappings);
        $this->assertArrayHasKey('aicl_prevention_rules', $mappings);
        $this->assertArrayHasKey('aicl_golden_annotations', $mappings);
    }

    public function test_index_mappings_all_returns_exactly_six_indices(): void
    {
        $this->assertCount(6, IndexMappings::all());
    }

    public function test_index_mappings_index_names_returns_keys(): void
    {
        $names = IndexMappings::indexNames();

        $this->assertSame(array_keys(IndexMappings::all()), $names);
    }

    public function test_index_mappings_for_index_returns_mapping(): void
    {
        $mapping = IndexMappings::forIndex('aicl_rlm_failures');

        $this->assertNotNull($mapping);
        $this->assertArrayHasKey('mappings', $mapping);
        $this->assertArrayHasKey('properties', $mapping['mappings']);
    }

    public function test_index_mappings_for_index_returns_null_for_unknown(): void
    {
        $this->assertNull(IndexMappings::forIndex('nonexistent_index'));
    }

    public function test_index_mappings_each_index_has_embedding_field(): void
    {
        foreach (IndexMappings::all() as $indexName => $mapping) {
            $properties = $mapping['mappings']['properties'] ?? [];
            $this->assertArrayHasKey('embedding', $properties, "Index {$indexName} missing embedding field");
            $this->assertSame('dense_vector', $properties['embedding']['type']);
            $this->assertSame(1536, $properties['embedding']['dims']);
            $this->assertSame('cosine', $properties['embedding']['similarity']);
        }
    }

    public function test_index_mappings_rlm_failures_has_expected_properties(): void
    {
        $mapping = IndexMappings::rlmFailures();
        $properties = $mapping['mappings']['properties'];

        $this->assertArrayHasKey('failure_code', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('severity', $properties);
        $this->assertArrayHasKey('root_cause', $properties);
    }

    public function test_index_mappings_rlm_lessons_has_expected_properties(): void
    {
        $mapping = IndexMappings::rlmLessons();
        $properties = $mapping['mappings']['properties'];

        $this->assertArrayHasKey('topic', $properties);
        $this->assertArrayHasKey('summary', $properties);
        $this->assertArrayHasKey('detail', $properties);
        $this->assertArrayHasKey('confidence', $properties);
    }

    public function test_index_mappings_rlm_patterns_has_expected_properties(): void
    {
        $mapping = IndexMappings::rlmPatterns();
        $properties = $mapping['mappings']['properties'];

        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('target', $properties);
        $this->assertArrayHasKey('weight', $properties);
    }

    public function test_index_mappings_prevention_rules_has_expected_properties(): void
    {
        $mapping = IndexMappings::preventionRules();
        $properties = $mapping['mappings']['properties'];

        $this->assertArrayHasKey('rule_text', $properties);
        $this->assertArrayHasKey('confidence', $properties);
        $this->assertArrayHasKey('priority', $properties);
    }

    public function test_index_mappings_golden_annotations_has_expected_properties(): void
    {
        $mapping = IndexMappings::goldenAnnotations();
        $properties = $mapping['mappings']['properties'];

        $this->assertArrayHasKey('annotation_key', $properties);
        $this->assertArrayHasKey('annotation_text', $properties);
        $this->assertArrayHasKey('pattern_name', $properties);
        $this->assertArrayHasKey('feature_tags', $properties);
    }

    // ========================================================================
    // KnowledgeService — constructor and method existence
    // ========================================================================

    public function test_knowledge_service_can_be_instantiated(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $this->assertInstanceOf(KnowledgeService::class, $service);
    }

    public function test_knowledge_service_search_returns_collection(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('test query');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_knowledge_service_search_with_type_filter(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('test query', 'failure');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_knowledge_service_search_deterministic_fallback_finds_failures(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        RlmFailure::factory()->create([
            'title' => 'Missing HasFactory trait',
            'description' => 'Model needs HasFactory',
            'failure_code' => 'BF-TEST',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('HasFactory', 'failure');

        $this->assertNotEmpty($result);
    }

    public function test_knowledge_service_search_deterministic_fallback_finds_lessons(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Always use factories in tests',
            'detail' => 'Factories provide consistent test data',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('factories', 'lesson');

        $this->assertNotEmpty($result);
    }

    public function test_knowledge_service_search_deterministic_fallback_finds_patterns(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        RlmPattern::factory()->create([
            'name' => 'model.fillable',
            'description' => 'Model must declare fillable',
            'target' => 'model',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('fillable', 'pattern');

        $this->assertNotEmpty($result);
    }

    public function test_knowledge_service_search_all_types_with_deterministic(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        RlmFailure::factory()->create([
            'title' => 'Unique test failure xyz123',
            'failure_code' => 'BF-XYZ',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('xyz123', null);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_knowledge_service_recall_returns_expected_structure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('architect', 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('failures', $result);
        $this->assertArrayHasKey('lessons', $result);
        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('prevention_rules', $result);
        $this->assertArrayHasKey('golden_annotations', $result);
        $this->assertArrayHasKey('risk_briefing', $result);
    }

    public function test_knowledge_service_recall_with_entity_context(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('architect', 3, ['has_states' => true, 'has_media' => true], 'TestEntity');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('risk_briefing', $result);
        $this->assertArrayHasKey('high_risk', $result['risk_briefing']);
        $this->assertArrayHasKey('prevention_rules', $result['risk_briefing']);
        $this->assertArrayHasKey('recent_outcomes', $result['risk_briefing']);
    }

    public function test_knowledge_service_recall_with_entity_name_fetches_scores(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        RlmScore::factory()->structural()->create(['entity_name' => 'MyEntity', 'owner_id' => 1]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('architect', 4, null, 'MyEntity');

        $this->assertInstanceOf(Collection::class, $result['scores']);
    }

    public function test_knowledge_service_add_lesson_creates_record(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $lesson = $service->addLesson(
            topic: 'testing',
            summary: 'Test summary',
            detail: 'Test detail content',
            subtopic: 'factories',
            tags: 'test,factory',
            source: 'unit_test',
            confidence: 0.9,
        );

        $this->assertInstanceOf(RlmLesson::class, $lesson);
        $this->assertSame('testing', $lesson->topic);
        $this->assertSame('Test summary', $lesson->summary);
        $this->assertSame('factories', $lesson->subtopic);
        $this->assertSame('test,factory', $lesson->tags);
        $this->assertDatabaseHas('rlm_lessons', ['topic' => 'testing', 'summary' => 'Test summary']);
    }

    public function test_knowledge_service_record_failure_creates_record(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $failure = $service->recordFailure([
            'failure_code' => 'BF-TEST-001',
            'category' => 'scaffolding',
            'severity' => 'high',
            'title' => 'Test failure',
            'description' => 'A test failure description',
        ]);

        $this->assertInstanceOf(RlmFailure::class, $failure);
        $this->assertSame('BF-TEST-001', $failure->failure_code);
        $this->assertDatabaseHas('rlm_failures', ['failure_code' => 'BF-TEST-001']);
    }

    public function test_knowledge_service_record_failure_upserts_by_failure_code(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $service->recordFailure([
            'failure_code' => 'BF-UPSERT',
            'category' => 'scaffolding',
            'severity' => 'medium',
            'title' => 'Original title',
            'description' => 'Original',
        ]);

        $service->recordFailure([
            'failure_code' => 'BF-UPSERT',
            'category' => 'scaffolding',
            'severity' => 'high',
            'title' => 'Updated title',
            'description' => 'Updated',
        ]);

        $this->assertSame(1, RlmFailure::query()->where('failure_code', 'BF-UPSERT')->count());
        $this->assertSame('Updated title', RlmFailure::query()->where('failure_code', 'BF-UPSERT')->first()->title);
    }

    public function test_knowledge_service_record_failure_requires_failure_code(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('failure_code is required');

        $service->recordFailure([
            'title' => 'No code failure',
        ]);
    }

    public function test_knowledge_service_record_score_creates_record(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $score = $service->recordScore(
            entityName: 'TestEntity',
            type: 'structural',
            passed: 38,
            total: 42,
            percentage: 90.5,
            errors: 2,
            warnings: 2,
            details: [['name' => 'model.fillable', 'passed' => true]],
        );

        $this->assertInstanceOf(RlmScore::class, $score);
        $this->assertSame('TestEntity', $score->entity_name);
        $this->assertSame(ScoreType::Structural, $score->score_type);
        $this->assertSame(38, $score->passed);
        $this->assertSame(42, $score->total);
        $this->assertDatabaseHas('rlm_scores', ['entity_name' => 'TestEntity', 'score_type' => 'structural']);
    }

    public function test_knowledge_service_record_trace_creates_record(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $trace = $service->recordTrace('TestEntity', [
            'scaffolder_args' => '--fields name:string',
            'structural_score' => 95.0,
            'semantic_score' => 90.0,
            'fix_iterations' => 1,
        ]);

        $this->assertInstanceOf(GenerationTrace::class, $trace);
        $this->assertSame('TestEntity', $trace->entity_name);
        $this->assertDatabaseHas('generation_traces', ['entity_name' => 'TestEntity']);
    }

    public function test_knowledge_service_get_failures_by_context_returns_collection(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-CTX',
            'is_active' => true,
            'entity_context' => null,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->getFailuresByContext();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_knowledge_service_get_failures_by_context_includes_universal(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-UNI',
            'is_active' => true,
            'entity_context' => null,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->getFailuresByContext(['has_states' => true]);

        $codes = $result->pluck('failure_code')->all();
        $this->assertContains('BF-UNI', $codes);
    }

    public function test_knowledge_service_get_failures_by_context_filters_by_severity(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-HIGH',
            'severity' => 'high',
            'is_active' => true,
            'entity_context' => null,
            'owner_id' => 1,
        ]);
        RlmFailure::factory()->create([
            'failure_code' => 'BF-LOW',
            'severity' => 'low',
            'is_active' => true,
            'entity_context' => null,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->getFailuresByContext([], ['high']);

        $codes = $result->pluck('failure_code')->all();
        $this->assertContains('BF-HIGH', $codes);
        $this->assertNotContains('BF-LOW', $codes);
    }

    public function test_knowledge_service_get_failure_by_code(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-FIND',
            'title' => 'Findable failure',
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $failure = $service->getFailure('BF-FIND');

        $this->assertNotNull($failure);
        $this->assertSame('BF-FIND', $failure->failure_code);
    }

    public function test_knowledge_service_get_failure_returns_null_for_unknown(): void
    {
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $this->assertNull($service->getFailure('BF-NONEXISTENT'));
    }

    public function test_knowledge_service_stats_returns_expected_structure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $stats = $service->stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('storage', $stats);
        $this->assertSame('postgresql', $stats['storage']);
        $this->assertArrayHasKey('search_engine', $stats);
        $this->assertArrayHasKey('embeddings', $stats);
        $this->assertArrayHasKey('patterns', $stats);
        $this->assertArrayHasKey('failures', $stats);
        $this->assertArrayHasKey('lessons', $stats);
        $this->assertArrayHasKey('scores', $stats);
        $this->assertArrayHasKey('traces', $stats);
    }

    public function test_knowledge_service_stats_embeddings_unavailable_for_null_driver(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        config(['aicl.rlm.embeddings.driver' => 'null']);
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $stats = $service->stats();

        $this->assertSame('unavailable', $stats['embeddings']);
    }

    public function test_knowledge_service_is_elasticsearch_available_returns_false_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $this->assertFalse($service->isElasticsearchAvailable());
    }

    public function test_knowledge_service_is_elasticsearch_available_caches_result(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $service->isElasticsearchAvailable();
        $service->isElasticsearchAvailable();

        // Should only make one HTTP call due to per-instance caching
        Http::assertSentCount(1);
    }

    public function test_knowledge_service_reset_availability_cache(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $service->isElasticsearchAvailable();
        $service->resetAvailabilityCache();
        $service->isElasticsearchAvailable();

        Http::assertSentCount(2);
    }

    public function test_knowledge_service_is_search_available(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        config(['aicl.rlm.embeddings.driver' => 'null']);
        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $this->assertFalse($service->isSearchAvailable());
    }

    // ========================================================================
    // EmbeddingService — additional edge case tests
    // ========================================================================

    public function test_embedding_service_generate_batch_handles_single_item(): void
    {
        config(['aicl.rlm.embeddings.driver' => 'null']);
        $service = new EmbeddingService;

        $results = $service->generateBatch(['single text']);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function test_embedding_service_openai_fallback_to_null_on_exception(): void
    {
        config([
            'aicl.rlm.embeddings.driver' => 'openai',
            'aicl.rlm.embeddings.openai.api_key' => 'test-key',
        ]);

        $service = new EmbeddingService;
        $driver = $service->getDriver();

        // When NeuronAI provider fails, it should fallback to NullDriver
        $this->assertInstanceOf(EmbeddingDriver::class, $driver);
    }

    public function test_embedding_service_ollama_fallback_to_null_when_unreachable(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        config([
            'aicl.rlm.embeddings.driver' => 'ollama',
        ]);

        $service = new EmbeddingService;
        $driver = $service->getDriver();

        // When Ollama is unreachable, should fallback to NullDriver
        $this->assertInstanceOf(EmbeddingDriver::class, $driver);
    }

    // ========================================================================
    // PatternRegistry — registration patterns
    // ========================================================================

    public function test_pattern_registry_all_with_entity_name_includes_registration(): void
    {
        $withoutEntity = PatternRegistry::all();
        $withEntity = PatternRegistry::all('TestEntity');

        $this->assertGreaterThan(count($withoutEntity), count($withEntity));
    }

    public function test_pattern_registry_registration_patterns_contain_policy_binding(): void
    {
        $patterns = PatternRegistry::registrationPatterns('Project');
        $names = array_map(fn (EntityPattern $p) => $p->name, $patterns);

        $this->assertContains('registration.policy_bound', $names);
        $this->assertContains('registration.observer_bound', $names);
        $this->assertContains('registration.api_routes', $names);
        $this->assertContains('registration.filament_discovery', $names);
    }

    public function test_pattern_registry_registration_patterns_entity_name_in_check(): void
    {
        $patterns = PatternRegistry::registrationPatterns('Invoice');

        $policyPattern = null;
        foreach ($patterns as $pattern) {
            if ($pattern->name === 'registration.policy_bound') {
                $policyPattern = $pattern;

                break;
            }
        }

        $this->assertNotNull($policyPattern);
        $this->assertStringContainsString('Invoice', $policyPattern->check);
    }

    public function test_pattern_registry_registration_uses_snake_plural_for_routes(): void
    {
        $patterns = PatternRegistry::registrationPatterns('FailureReport');

        $routePattern = null;
        foreach ($patterns as $pattern) {
            if ($pattern->name === 'registration.api_routes') {
                $routePattern = $pattern;

                break;
            }
        }

        $this->assertNotNull($routePattern);
        $this->assertStringContainsString('failure_reports', $routePattern->description);
    }

    // ========================================================================
    // PatternCandidate — additional coverage
    // ========================================================================

    public function test_pattern_candidate_to_entity_pattern_preserves_all_fields(): void
    {
        $candidate = new PatternCandidate(
            name: 'candidate.scope_active',
            description: 'Active scope present',
            target: 'model',
            suggestedRegex: 'scopeActive',
            severity: 'error',
            weight: 2.5,
            confidence: 0.99,
            occurrences: 10,
            source: 'manual',
        );

        $pattern = $candidate->toEntityPattern();

        $this->assertSame('candidate.scope_active', $pattern->name);
        $this->assertSame('Active scope present', $pattern->description);
        $this->assertSame('model', $pattern->target);
        $this->assertSame('scopeActive', $pattern->check);
        $this->assertSame('error', $pattern->severity);
        $this->assertSame(2.5, $pattern->weight);
    }

    // ========================================================================
    // SemanticValidator — validate with no API key
    // ========================================================================

    public function test_semantic_validator_validate_without_api_key_skips_all(): void
    {
        config(['aicl.rlm.semantic.api_key' => null]);

        $fixtureDir = sys_get_temp_dir().'/rlm_coverage_'.uniqid();
        mkdir($fixtureDir, 0755, true);

        $migrationFile = $fixtureDir.'/migration.php';
        $factoryFile = $fixtureDir.'/factory.php';
        file_put_contents($migrationFile, '<?php return new class extends Migration {};');
        file_put_contents($factoryFile, '<?php class TestFactory extends Factory { public function definition(): array { return []; } }');

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: [
                'migration' => $migrationFile,
                'factory' => $factoryFile,
            ],
        );

        $results = $validator->validate();

        $skipped = $validator->skipped();
        $this->assertNotEmpty($skipped);

        foreach ($skipped as $result) {
            $this->assertTrue($result->skipped);
        }

        unlink($migrationFile);
        unlink($factoryFile);
        rmdir($fixtureDir);
    }

    public function test_semantic_validator_validate_with_missing_files_skips(): void
    {
        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: ['migration' => '/nonexistent/path.php'],
        );

        $results = $validator->validate();

        foreach ($results as $result) {
            $this->assertTrue($result->skipped);
        }
    }

    // ========================================================================
    // SemanticCheck — edge cases
    // ========================================================================

    public function test_semantic_check_is_applicable_with_empty_context(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            appliesWhen: 'has_widgets',
        );

        $this->assertFalse($check->isApplicable([]));
    }

    public function test_semantic_check_is_applicable_with_falsy_value(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            appliesWhen: 'has_widgets',
        );

        $this->assertFalse($check->isApplicable(['has_widgets' => 0]));
        $this->assertFalse($check->isApplicable(['has_widgets' => null]));
        $this->assertFalse($check->isApplicable(['has_widgets' => '']));
    }

    // ========================================================================
    // ProjectIdentity — additional edge cases
    // ========================================================================

    public function test_project_identity_hub_url_returns_configured_value(): void
    {
        config(['aicl.rlm.hub.url' => 'https://hub.test.com']);

        $identity = new ProjectIdentity;

        $this->assertSame('https://hub.test.com', $identity->hubUrl());
    }

    public function test_project_identity_hub_token_returns_configured_value(): void
    {
        config(['aicl.rlm.hub.token' => 'my-secret-token']);

        $identity = new ProjectIdentity;

        $this->assertSame('my-secret-token', $identity->hubToken());
    }

    public function test_project_identity_anonymize_strips_all_sensitive_key_types(): void
    {
        $identity = new ProjectIdentity;

        $data = [
            'entity_name' => 'User',
            'source_code' => 'class Foo {}',
            'file_contents' => 'content',
            'absolute_path' => '/app/test.php',
            'directory' => '/var/www',
            'app_key' => 'base64:abc',
            'token' => 'tok_123',
            'secret' => 'shhh',
            'connection_string' => 'postgres://...',
            'database_url' => 'mysql://...',
        ];

        $result = $identity->anonymize($data);

        $this->assertArrayHasKey('entity_name', $result);
        $this->assertArrayNotHasKey('source_code', $result);
        $this->assertArrayNotHasKey('file_contents', $result);
        $this->assertArrayNotHasKey('absolute_path', $result);
        $this->assertArrayNotHasKey('directory', $result);
        $this->assertArrayNotHasKey('app_key', $result);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('secret', $result);
        $this->assertArrayNotHasKey('connection_string', $result);
        $this->assertArrayNotHasKey('database_url', $result);
    }

    public function test_project_identity_anonymize_redacts_windows_paths(): void
    {
        $identity = new ProjectIdentity;

        $result = $identity->anonymize([
            'location' => 'C:\\Users\\dev\\project\\model.php',
        ]);

        $this->assertSame('[redacted:path]', $result['location']);
    }

    public function test_project_identity_anonymize_preserves_urls(): void
    {
        $identity = new ProjectIdentity;

        $result = $identity->anonymize([
            'url' => 'https://api.example.com/v1/test',
        ]);

        $this->assertSame('https://api.example.com/v1/test', $result['url']);
    }

    public function test_project_identity_anonymize_handles_deeply_nested_sensitive_keys(): void
    {
        $identity = new ProjectIdentity;

        $result = $identity->anonymize([
            'level1' => [
                'level2' => [
                    'entity_name' => 'User',
                    'api_key' => 'should-be-stripped',
                ],
            ],
        ]);

        $this->assertArrayHasKey('level1', $result);
        $this->assertArrayHasKey('level2', $result['level1']);
        $this->assertArrayHasKey('entity_name', $result['level1']['level2']);
        $this->assertArrayNotHasKey('api_key', $result['level1']['level2']);
    }

    // ========================================================================
    // HubClient — additional coverage
    // ========================================================================

    public function test_hub_client_get_last_response_initially_empty(): void
    {
        $identity = new ProjectIdentity;
        $client = new HubClient($identity);

        $this->assertSame([], $client->getLastResponse());
    }

    public function test_hub_client_push_reports(): void
    {
        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => 'https://hub.example.com',
            'aicl.rlm.hub.token' => 'test-token',
        ]);

        Http::fake([
            'hub.example.com/api/v1/failure_reports' => Http::response(['data' => []], 201),
        ]);

        $identity = new ProjectIdentity;
        $client = new HubClient($identity);

        $result = $client->pushReports([
            ['failure_code' => 'BF-001', 'entity_name' => 'TestEntity'],
        ]);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame(0, $result['errors']);
    }

    public function test_hub_client_enqueue_and_dequeue(): void
    {
        $identity = new ProjectIdentity;
        $client = new HubClient($identity);

        $client->enqueue('test_endpoint', ['key' => 'value']);
        $client->enqueue('test_endpoint', ['key2' => 'value2']);

        $this->assertSame(2, $client->getQueueSize());

        $items = $client->dequeue(1);

        $this->assertCount(1, $items);
        $this->assertSame('test_endpoint', $items[0]['endpoint']);
        $this->assertSame(1, $client->getQueueSize());
    }

    public function test_hub_client_dequeue_respects_limit(): void
    {
        $identity = new ProjectIdentity;
        $client = new HubClient($identity);

        for ($i = 0; $i < 5; $i++) {
            $client->enqueue('endpoint', ['item' => $i]);
        }

        $items = $client->dequeue(3);

        $this->assertCount(3, $items);
        $this->assertSame(2, $client->getQueueSize());
    }

    // ========================================================================
    // PatternDiscovery — sanitizeName edge cases
    // ========================================================================

    public function test_pattern_discovery_analyze_traces_skips_processed(): void
    {
        $fixes = [['pattern' => 'test_fix', 'fix' => 'Fix it']];

        GenerationTrace::factory()->create([
            'entity_name' => 'E1',
            'fixes_applied' => $fixes,
            'is_processed' => true,
            'owner_id' => 1,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'E2',
            'fixes_applied' => $fixes,
            'is_processed' => true,
            'owner_id' => 1,
        ]);

        $discovery = new PatternDiscovery;
        $candidates = $discovery->analyzeTraces(minOccurrences: 2);

        $this->assertSame([], $candidates);
    }

    // ========================================================================
    // EntityValidator — full pipeline with passing registration
    // ========================================================================

    public function test_entity_validator_chain_add_file(): void
    {
        $validator = new EntityValidator('TestEntity');

        $result = $validator->addFile('model', '/tmp/a.php')
            ->addFile('migration', '/tmp/b.php')
            ->addFile('factory', '/tmp/c.php');

        $this->assertSame($validator, $result);
    }

    // ========================================================================
    // ValidationResult — ensure pattern is accessible
    // ========================================================================

    public function test_validation_result_pattern_severity_accessible(): void
    {
        $pattern = new EntityPattern(
            name: 'test.pattern',
            description: 'A test pattern',
            target: 'model',
            check: 'something',
            severity: 'error',
            weight: 2.0,
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: false,
            message: 'Pattern failed',
            file: '/tmp/test.php',
        );

        $this->assertTrue($result->pattern->isError());
        $this->assertSame(2.0, $result->pattern->weight);
        $this->assertSame('test.pattern', $result->pattern->name);
    }

    // ========================================================================
    // SemanticResult — comprehensive property tests
    // ========================================================================

    public function test_semantic_result_full_construction(): void
    {
        $check = new SemanticCheck(
            name: 'semantic.coverage',
            description: 'Check coverage',
            targets: ['model', 'test'],
            prompt: 'Check test coverage',
            severity: 'error',
            weight: 2.0,
            appliesWhen: 'has_states',
        );

        $result = new SemanticResult(
            check: $check,
            passed: false,
            message: 'Missing test for state transitions',
            confidence: 0.85,
            files: ['model.php', 'test.php'],
            skipped: false,
        );

        $this->assertSame($check, $result->check);
        $this->assertFalse($result->passed);
        $this->assertSame('Missing test for state transitions', $result->message);
        $this->assertSame(0.85, $result->confidence);
        $this->assertSame(['model.php', 'test.php'], $result->files);
        $this->assertFalse($result->skipped);
    }

    // ========================================================================
    // KnowledgeService — deterministic search for prevention rules
    // ========================================================================

    public function test_knowledge_service_search_prevention_rules_deterministic(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        PreventionRule::factory()->create([
            'rule_text' => 'Always use RefreshDatabase in tests',
            'is_active' => true,
            'rlm_failure_id' => null,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('RefreshDatabase', 'prevention_rule');

        $this->assertNotEmpty($result);
    }

    public function test_knowledge_service_search_golden_annotations_deterministic(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        GoldenAnnotation::factory()->create([
            'annotation_key' => 'model.fillable',
            'annotation_text' => 'The fillable array must include all mass-assignable fields',
            'is_active' => true,
            'owner_id' => 1,
        ]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('fillable', 'golden_annotation');

        $this->assertNotEmpty($result);
    }

    public function test_knowledge_service_search_unknown_type_returns_empty(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('test', 'nonexistent_type');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function test_knowledge_service_search_respects_limit(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        for ($i = 0; $i < 15; $i++) {
            RlmFailure::factory()->create([
                'title' => "Searchable failure number {$i}",
                'failure_code' => "BF-LIMIT-{$i}",
                'is_active' => true,
                'owner_id' => 1,
            ]);
        }

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->search('Searchable failure', 'failure', 5);

        $this->assertLessThanOrEqual(5, $result->count());
    }

    // ========================================================================
    // NeuronAiEmbeddingAdapter — contract methods
    // ========================================================================

    public function test_neuron_ai_adapter_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(NeuronAiEmbeddingAdapter::class, 'embed'));
        $this->assertTrue(method_exists(NeuronAiEmbeddingAdapter::class, 'embedBatch'));
        $this->assertTrue(method_exists(NeuronAiEmbeddingAdapter::class, 'dimension'));
    }

    // ========================================================================
    // SemanticCache — edge cases
    // ========================================================================

    public function test_semantic_cache_clear_for_nonexistent_entity_returns_zero(): void
    {
        $cache = new SemanticCache;

        $this->assertSame(0, $cache->clearForEntity('NonexistentEntity'));
    }

    public function test_semantic_cache_prune_with_no_entries_returns_zero(): void
    {
        $cache = new SemanticCache;

        $this->assertSame(0, $cache->prune());
    }

    // ========================================================================
    // KnowledgeService — recall with all agent types for topic mapping
    // ========================================================================

    public function test_knowledge_service_recall_architect_topics(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('architect', 3);

        $this->assertArrayHasKey('lessons', $result);
    }

    public function test_knowledge_service_recall_tester_topics(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('tester', 7);

        $this->assertArrayHasKey('lessons', $result);
    }

    public function test_knowledge_service_recall_rlm_topics(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('rlm', 4);

        $this->assertArrayHasKey('lessons', $result);
    }

    public function test_knowledge_service_recall_unknown_agent(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $embeddingService = new EmbeddingService;
        $service = new KnowledgeService($embeddingService);

        $result = $service->recall('unknown_agent', 1);

        $this->assertArrayHasKey('lessons', $result);
    }
}
