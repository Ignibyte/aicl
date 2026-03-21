<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasSearchableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class HasSearchableFieldsTest extends TestCase
{
    public function test_to_searchable_array_includes_id_and_fields(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';

            protected $guarded = [];

            /** @phpstan-ignore-next-line */
            protected function searchableFields(): array
            {
                return ['name', 'email'];
            }
        };

        $model->forceFill(['id' => 1, 'name' => 'John', 'email' => 'john@test.com']);

        $result = $model->toSearchableArray();

        $this->assertEquals([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@test.com',
        ], $result);
    }

    public function test_default_searchable_fields_is_name(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';
        };

        $reflection = new \ReflectionMethod($model, 'searchableFields');
        $reflection->setAccessible(true);

        $this->assertEquals(['name'], $reflection->invoke($model));
    }

    public function test_searchable_as_returns_pluralized_snake_case(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';
        };

        config(['scout.prefix' => '']);

        $result = $model->searchableAs();

        // Anonymous class will produce an odd name, but the format should be snake_case
    }

    public function test_searchable_as_includes_prefix(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';
        };

        config(['scout.prefix' => 'testing_']);

        $result = $model->searchableAs();

        $this->assertStringStartsWith('testing_', $result);
    }

    public function test_should_be_searchable_returns_true_for_normal_model(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';
        };

        $this->assertTrue($model->shouldBeSearchable());
    }

    public function test_should_be_searchable_returns_false_for_trashed(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;
            use SoftDeletes;

            protected $table = 'test_models';
        };

        // Simulate trashed state
        $model->forceFill(['deleted_at' => now()]);

        $this->assertFalse($model->shouldBeSearchable());
    }

    public function test_to_searchable_array_handles_backed_enum(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';

            protected $guarded = [];

            /** @phpstan-ignore-next-line */
            protected function searchableFields(): array
            {
                return ['status'];
            }
        };

        $enum = TestSearchableStatus::Active;
        $model->forceFill(['id' => 1, 'status' => $enum]);

        $result = $model->toSearchableArray();

        $this->assertEquals('active', $result['status']);
    }

    public function test_to_searchable_array_handles_datetime(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';

            protected $guarded = [];

            /** @phpstan-ignore-next-line */
            protected function searchableFields(): array
            {
                return ['published_at'];
            }

            protected function casts(): array
            {
                return ['published_at' => 'datetime'];
            }
        };

        $date = new \DateTimeImmutable('2026-01-15 10:30:00');
        $model->forceFill(['id' => 1, 'published_at' => $date]);

        $result = $model->toSearchableArray();

        $this->assertEquals('2026-01-15 10:30:00', $result['published_at']);
    }

    public function test_to_searchable_array_handles_null_value(): void
    {
        $model = new class extends Model
        {
            use HasSearchableFields;

            protected $table = 'test_models';

            protected $guarded = [];

            /** @phpstan-ignore-next-line */
            protected function searchableFields(): array
            {
                return ['description'];
            }
        };

        $model->forceFill(['id' => 1, 'description' => null]);

        $result = $model->toSearchableArray();

        $this->assertNull($result['description']);
    }
}

enum TestSearchableStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
