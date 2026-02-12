<?php

namespace Aicl\Tests\Feature\Rlm;

use Aicl\Rlm\SemanticCache;
use Aicl\Rlm\SemanticValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemanticValidatorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aicl.rlm.semantic.api_key' => 'test-api-key',
            'aicl.rlm.semantic.model' => 'claude-haiku-4-5-20251001',
            'aicl.rlm.semantic.max_tokens' => 1024,
            'aicl.rlm.semantic.timeout' => 30,
            'aicl.rlm.semantic.confidence_threshold' => 0.3,
            'aicl.rlm.semantic.use_cache' => false,
        ]);

        $this->tempDir = sys_get_temp_dir().'/semantic_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);

        // Create sample entity files
        $this->createSampleFiles();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir.'/*'));
        rmdir($this->tempDir);

        parent::tearDown();
    }

    public function test_validate_with_all_passing(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "All good", "confidence": 0.95}'],
                ],
            ], 200),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $results = $validator->validate();

        $this->assertNotEmpty($results);
        $this->assertSame(100.0, $validator->score());
        $this->assertEmpty($validator->failures());
    }

    public function test_validate_with_some_failures(): void
    {
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            $passed = $callCount % 2 === 0;

            return Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'passed' => $passed,
                        'message' => $passed ? 'OK' : 'Issue found',
                        'confidence' => 0.9,
                    ])],
                ],
            ], 200);
        });

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $validator->validate();

        $this->assertNotEmpty($validator->failures());
        $this->assertLessThan(100.0, $validator->score());
    }

    public function test_validate_without_api_key_skips_all(): void
    {
        config(['aicl.rlm.semantic.api_key' => null]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $results = $validator->validate();

        foreach ($results as $result) {
            $this->assertTrue($result->skipped);
            $this->assertStringContainsString('API key', $result->message);
        }

        // Skipped checks shouldn't count in score
        $this->assertSame(100.0, $validator->score());
    }

    public function test_validate_with_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $validator->validate();

        foreach ($validator->results() as $result) {
            $this->assertTrue($result->skipped);
            $this->assertStringContainsString('429', $result->message);
        }
    }

    public function test_validate_with_timeout(): void
    {
        Http::fake([
            'api.anthropic.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            },
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $validator->validate();

        foreach ($validator->results() as $result) {
            $this->assertTrue($result->skipped);
        }
    }

    public function test_validate_with_malformed_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'This is not JSON, just a plain text response with no structure.'],
                ],
            ], 200),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $validator->validate();

        foreach ($validator->results() as $result) {
            $this->assertTrue($result->skipped);
            $this->assertStringContainsString('Could not parse JSON', $result->message);
        }
    }

    public function test_validate_skips_checks_with_missing_files(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
                ],
            ], 200),
        ]);

        // Only provide migration — missing factory, controller, etc.
        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: ['migration' => $this->tempDir.'/migration.php'],
        );

        $results = $validator->validate();

        $skipped = array_filter($results, fn ($r) => $r->skipped);
        $this->assertNotEmpty($skipped);
    }

    public function test_validate_with_entity_context_filtering(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
                ],
            ], 200),
        ]);

        // Without states/widgets context — those conditional checks should be skipped as not applicable
        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
            entityContext: [], // No states or widgets
        );

        $results = $validator->validate();
        $names = array_map(fn ($r) => $r->check->name, $results);

        $this->assertNotContains('semantic.widget_queries', $names);
        $this->assertNotContains('semantic.state_transitions', $names);
    }

    public function test_validate_with_cache_enabled(): void
    {
        config(['aicl.rlm.semantic.use_cache' => true]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
                ],
            ], 200),
        ]);

        $cache = new SemanticCache;

        $files = $this->getFileMap();

        // First run — should hit API
        $validator1 = new SemanticValidator(
            entityName: 'TestEntity',
            files: $files,
            cache: $cache,
        );
        $validator1->validate();

        // Second run — should use cache (no API calls)
        Http::fake(); // Reset — any API call would fail
        $validator2 = new SemanticValidator(
            entityName: 'TestEntity',
            files: $files,
            cache: $cache,
        );
        $results = $validator2->validate();

        // All non-skipped results should be from cache
        $nonSkipped = array_filter($results, fn ($r) => ! $r->skipped);
        foreach ($nonSkipped as $result) {
            $this->assertTrue($result->passed);
            $this->assertSame('OK', $result->message);
        }
    }

    public function test_validate_sends_correct_headers(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
                ],
            ], 200),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );
        $validator->validate();

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'test-api-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request->url() === 'https://api.anthropic.com/v1/messages';
        });
    }

    public function test_validate_sends_correct_model(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
                ],
            ], 200),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );
        $validator->validate();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['model'] ?? '') === 'claude-haiku-4-5-20251001'
                && ($body['max_tokens'] ?? 0) === 1024;
        });
    }

    public function test_low_confidence_response_treated_as_skipped(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"passed": false, "message": "Unsure", "confidence": 0.1}'],
                ],
            ], 200),
        ]);

        $validator = new SemanticValidator(
            entityName: 'TestEntity',
            files: $this->getFileMap(),
        );

        $validator->validate();

        foreach ($validator->results() as $result) {
            if (! $result->skipped) {
                continue;
            }
            $this->assertStringContainsString('Low confidence', $result->message);
        }

        // Score should be 100% because all are skipped
        $this->assertSame(100.0, $validator->score());
    }

    private function createSampleFiles(): void
    {
        file_put_contents($this->tempDir.'/migration.php', <<<'PHP'
            <?php
            return new class extends \Illuminate\Database\Migrations\Migration {
                public function up(): void
                {
                    Schema::create('test_entities', function ($table) {
                        $table->id();
                        $table->string('name');
                        $table->boolean('is_active')->default(true);
                        $table->foreignId('owner_id')->constrained('users');
                        $table->timestamps();
                        $table->softDeletes();
                    });
                }
            };
            PHP);

        file_put_contents($this->tempDir.'/factory.php', <<<'PHP'
            <?php
            class TestEntityFactory extends \Illuminate\Database\Eloquent\Factories\Factory {
                public function definition(): array
                {
                    return [
                        'name' => fake()->sentence(),
                        'is_active' => fake()->boolean(),
                        'owner_id' => \App\Models\User::factory(),
                    ];
                }
            }
            PHP);

        file_put_contents($this->tempDir.'/model.php', <<<'PHP'
            <?php
            class TestEntity extends \Illuminate\Database\Eloquent\Model {
                use \Illuminate\Database\Eloquent\SoftDeletes;
                protected $fillable = ['name', 'is_active', 'owner_id'];
                public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
                {
                    return $this->belongsTo(\App\Models\User::class, 'owner_id');
                }
                public function searchableColumns(): array { return ['name']; }
            }
            PHP);

        file_put_contents($this->tempDir.'/controller.php', <<<'PHP'
            <?php
            class TestEntityController extends \Illuminate\Routing\Controller {
                public function index() { $this->authorize('viewAny', TestEntity::class); }
                public function store() { $this->authorize('create', TestEntity::class); }
            }
            PHP);

        file_put_contents($this->tempDir.'/policy.php', <<<'PHP'
            <?php
            class TestEntityPolicy {
                public function viewAny($user) { return true; }
                public function create($user) { return true; }
                public function update($user, $entity) { return $user->id === $entity->owner_id; }
                public function delete($user, $entity) { return $user->id === $entity->owner_id; }
            }
            PHP);

        file_put_contents($this->tempDir.'/api_resource.php', <<<'PHP'
            <?php
            class TestEntityResource extends \Illuminate\Http\Resources\Json\JsonResource {
                public function toArray($request): array
                {
                    return ['id' => $this->id, 'name' => $this->name, 'is_active' => $this->is_active];
                }
            }
            PHP);

        file_put_contents($this->tempDir.'/form_request.php', <<<'PHP'
            <?php
            class StoreTestEntityRequest extends \Illuminate\Foundation\Http\FormRequest {
                public function rules(): array
                {
                    return ['name' => 'required|string|max:255', 'is_active' => 'nullable|boolean'];
                }
            }
            PHP);

        file_put_contents($this->tempDir.'/test.php', <<<'PHP'
            <?php
            class TestEntityTest extends \Tests\TestCase {
                public function test_can_create(): void {}
                public function test_owner_relationship(): void {}
                public function test_soft_delete(): void {}
                public function test_policy(): void {}
                public function test_search_scope(): void {}
            }
            PHP);
    }

    /**
     * @return array<string, string>
     */
    private function getFileMap(): array
    {
        return [
            'migration' => $this->tempDir.'/migration.php',
            'factory' => $this->tempDir.'/factory.php',
            'model' => $this->tempDir.'/model.php',
            'controller' => $this->tempDir.'/controller.php',
            'policy' => $this->tempDir.'/policy.php',
            'api_resource' => $this->tempDir.'/api_resource.php',
            'form_request' => $this->tempDir.'/form_request.php',
            'test' => $this->tempDir.'/test.php',
        ];
    }
}
