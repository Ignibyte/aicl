<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasAiContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class HasAiContextTest extends TestCase
{
    // ========================================================================
    // toAiContext() — structure
    // ========================================================================

    public function test_to_ai_context_returns_expected_structure(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 42,
            'name' => 'Test Widget',
        ], ['name']);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('type', $context);
        $this->assertArrayHasKey('id', $context);
        $this->assertArrayHasKey('label', $context);
        $this->assertArrayHasKey('attributes', $context);
        $this->assertArrayHasKey('relationships', $context);
        $this->assertArrayHasKey('meta', $context);
    }

    public function test_to_ai_context_type_is_human_readable(): void
    {
        $model = $this->createModelWithAttributes(['id' => 1], []);

        $context = $model->toAiContext();

        // The type is derived from the class basename via Str::headline
        $this->assertIsString($context['type']);
        $this->assertNotEmpty($context['type']);
    }

    public function test_to_ai_context_id_matches_model_key(): void
    {
        $model = $this->createModelWithAttributes(['id' => 99], []);

        $context = $model->toAiContext();

        $this->assertSame(99, $context['id']);
    }

    // ========================================================================
    // aiContextLabel() — fallback chain
    // ========================================================================

    public function test_label_uses_name_when_present(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'name' => 'My Widget',
            'title' => 'Widget Title',
        ], ['name', 'title']);

        $context = $model->toAiContext();

        $this->assertSame('My Widget', $context['label']);
    }

    public function test_label_falls_back_to_title_when_name_is_null(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'name' => null,
            'title' => 'Fallback Title',
        ], ['name', 'title']);

        $context = $model->toAiContext();

        $this->assertSame('Fallback Title', $context['label']);
    }

    public function test_label_falls_back_to_id_when_name_and_title_are_null(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 55,
        ], []);

        $context = $model->toAiContext();

        $this->assertSame('55', $context['label']);
    }

    // ========================================================================
    // aiContextAttributes() — handles various types
    // ========================================================================

    public function test_attributes_includes_fillable_fields(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'name' => 'Widget',
            'description' => 'A fine widget',
        ], ['name', 'description']);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('name', $context['attributes']);
        $this->assertSame('Widget', $context['attributes']['name']);
        $this->assertSame('A fine widget', $context['attributes']['description']);
    }

    public function test_attributes_converts_backed_enum_to_value(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'priority' => TestPriority::High,
        ], ['priority']);

        $context = $model->toAiContext();

        $this->assertSame('high', $context['attributes']['priority']);
    }

    public function test_attributes_converts_datetime_to_formatted_string(): void
    {
        $dateTime = new \DateTimeImmutable('2026-01-15 10:30:00');

        $model = $this->createModelWithAttributes([
            'id' => 1,
            'due_date' => $dateTime,
        ], ['due_date']);

        $context = $model->toAiContext();

        $this->assertSame('2026-01-15 10:30:00', $context['attributes']['due_date']);
    }

    public function test_attributes_converts_stringable_object_to_string(): void
    {
        $stringable = new class
        {
            public function __toString(): string
            {
                return 'stringified value';
            }
        };

        $model = $this->createModelWithAttributes([
            'id' => 1,
            'label' => $stringable,
        ], ['label']);

        $context = $model->toAiContext();

        $this->assertSame('stringified value', $context['attributes']['label']);
    }

    public function test_attributes_handles_null_values(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'description' => null,
        ], ['description']);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('description', $context['attributes']);
        $this->assertNull($context['attributes']['description']);
    }

    // ========================================================================
    // aiContextRelationships() — only includes loaded relations
    // ========================================================================

    public function test_relationships_empty_when_nothing_loaded(): void
    {
        $model = $this->createModelWithAttributes(['id' => 1], []);

        $context = $model->toAiContext();

        $this->assertEmpty($context['relationships']);
    }

    public function test_relationships_includes_loaded_belongs_to(): void
    {
        $related = $this->createSimpleModel(['id' => 10, 'name' => 'Parent']);

        $model = $this->createModelWithAttributes(['id' => 1], []);
        $model->setRelation('owner', $related);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('owner', $context['relationships']);
        $this->assertSame(10, $context['relationships']['owner']['id']);
        $this->assertSame('Parent', $context['relationships']['owner']['label']);
    }

    public function test_relationships_handles_null_relation(): void
    {
        $model = $this->createModelWithAttributes(['id' => 1], []);
        $model->setRelation('owner', null);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('owner', $context['relationships']);
        $this->assertNull($context['relationships']['owner']);
    }

    public function test_relationships_includes_loaded_collection(): void
    {
        $child1 = $this->createSimpleModel(['id' => 5, 'name' => 'Child A']);
        $child2 = $this->createSimpleModel(['id' => 6, 'title' => 'Child B']);

        $collection = new EloquentCollection([$child1, $child2]);

        $model = $this->createModelWithAttributes(['id' => 1], []);
        $model->setRelation('items', $collection);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('items', $context['relationships']);
        $this->assertCount(2, $context['relationships']['items']);
        $this->assertSame(5, $context['relationships']['items'][0]['id']);
        $this->assertSame('Child A', $context['relationships']['items'][0]['label']);
    }

    // ========================================================================
    // aiContextMeta() — timestamps, status, is_active
    // ========================================================================

    public function test_meta_includes_timestamps_when_present(): void
    {
        $createdAt = \Carbon\Carbon::parse('2026-01-10 08:00:00');
        $updatedAt = \Carbon\Carbon::parse('2026-02-10 12:00:00');

        $model = $this->createTimestampModel($createdAt, $updatedAt);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('created_at', $context['meta']);
        $this->assertArrayHasKey('updated_at', $context['meta']);
        $this->assertSame('2026-01-10 08:00:00', $context['meta']['created_at']);
        $this->assertSame('2026-02-10 12:00:00', $context['meta']['updated_at']);
    }

    public function test_meta_includes_string_status_when_present(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'status' => 'active',
        ], []);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('status', $context['meta']);
        $this->assertSame('active', $context['meta']['status']);
    }

    public function test_meta_includes_enum_status_as_value(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'status' => TestStatus::Active,
        ], []);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('status', $context['meta']);
        $this->assertSame('active', $context['meta']['status']);
    }

    public function test_meta_includes_is_active_as_boolean(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'is_active' => 1,
        ], []);

        $context = $model->toAiContext();

        $this->assertArrayHasKey('is_active', $context['meta']);
        $this->assertTrue($context['meta']['is_active']);
    }

    public function test_meta_excludes_is_active_when_null(): void
    {
        $model = $this->createModelWithAttributes([
            'id' => 1,
            'is_active' => null,
        ], []);

        $context = $model->toAiContext();

        // When is_active is null, getAttribute returns null and the condition fails
        $this->assertArrayNotHasKey('is_active', $context['meta']);
    }

    public function test_meta_is_empty_when_no_meta_attributes_present(): void
    {
        $model = $this->createModelWithAttributes(['id' => 1], []);

        $context = $model->toAiContext();

        $this->assertEmpty($context['meta']);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /**
     * Create a model with Carbon timestamps pre-set in the attributes array.
     * Uses $timestamps=false to prevent Eloquent from casting date columns via DB connection.
     */
    private function createTimestampModel(\Carbon\Carbon $createdAt, \Carbon\Carbon $updatedAt): Model
    {
        return new class($createdAt, $updatedAt) extends Model
        {
            use HasAiContext;

            public $timestamps = false;

            protected $guarded = [];

            public function __construct(
                private ?\Carbon\Carbon $createdAtValue = null,
                private ?\Carbon\Carbon $updatedAtValue = null,
            ) {
                parent::__construct(['id' => 1]);
            }

            public function getKeyName(): string
            {
                return 'id';
            }

            public function getFillable(): array
            {
                return [];
            }

            public function getAttribute($key): mixed
            {
                if ($key === 'created_at') {
                    return $this->createdAtValue;
                }
                if ($key === 'updated_at') {
                    return $this->updatedAtValue;
                }

                return parent::getAttribute($key);
            }
        };
    }

    /**
     * Create an anonymous Eloquent model with the HasAiContext trait.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $fillable
     */
    private function createModelWithAttributes(array $attributes, array $fillable): Model
    {
        return new class($attributes, $fillable) extends Model
        {
            use HasAiContext;

            protected $guarded = [];

            /**
             * @param  array<string, mixed>  $attributes
             * @param  array<int, string>  $fillableFields
             */
            public function __construct(array $attributes = [], private array $fillableFields = [])
            {
                parent::__construct($attributes);

                // Set the key if id is provided
                if (isset($attributes['id'])) {
                    $this->setAttribute('id', $attributes['id']);
                }
            }

            public function getKeyName(): string
            {
                return 'id';
            }

            public function getFillable(): array
            {
                return $this->fillableFields;
            }
        };
    }

    /**
     * Create a simple Eloquent model (without HasAiContext) for relationships.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSimpleModel(array $attributes): Model
    {
        return new class($attributes) extends Model
        {
            protected $guarded = [];

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
            }

            public function getKeyName(): string
            {
                return 'id';
            }
        };
    }
}

/**
 * Test enum for backed enum attribute conversion.
 */
enum TestPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

/**
 * Test enum for status meta conversion.
 */
enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
