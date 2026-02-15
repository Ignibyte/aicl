<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Console\Support\BaseSchemaInspector;
use Aicl\Contracts\DeclaresBaseSchema;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Tests\TestCase;

class RlmPatternBaseSchemaTest extends TestCase
{
    public function test_rlm_pattern_implements_declares_base_schema(): void
    {
        $this->assertInstanceOf(DeclaresBaseSchema::class, new RlmPattern);
    }

    public function test_base_schema_returns_all_required_keys(): void
    {
        $schema = RlmPattern::baseSchema();

        $this->assertArrayHasKey('columns', $schema);
        $this->assertArrayHasKey('traits', $schema);
        $this->assertArrayHasKey('contracts', $schema);
        $this->assertArrayHasKey('fillable', $schema);
        $this->assertArrayHasKey('casts', $schema);
        $this->assertArrayHasKey('relationships', $schema);
    }

    public function test_base_schema_columns_match_migration(): void
    {
        $schema = RlmPattern::baseSchema();
        $columnNames = array_column($schema['columns'], 'name');

        $expected = [
            'name',
            'description',
            'target',
            'check_regex',
            'severity',
            'weight',
            'category',
            'applies_when',
            'source',
            'is_active',
            'pass_count',
            'fail_count',
            'last_evaluated_at',
        ];

        foreach ($expected as $column) {
            $this->assertContains($column, $columnNames, "Column '{$column}' missing from baseSchema");
        }
    }

    public function test_base_schema_fillable_matches_model(): void
    {
        $schema = RlmPattern::baseSchema();
        $model = new RlmPattern;

        $this->assertEquals($model->getFillable(), $schema['fillable']);
    }

    public function test_base_schema_casts_match_model(): void
    {
        $schema = RlmPattern::baseSchema();
        $modelCasts = (new RlmPattern)->getCasts();

        foreach ($schema['casts'] as $key => $type) {
            $this->assertArrayHasKey($key, $modelCasts, "Cast '{$key}' missing from model");
        }
    }

    public function test_base_schema_relationships_include_owner(): void
    {
        $schema = RlmPattern::baseSchema();

        $this->assertCount(1, $schema['relationships']);
        $this->assertEquals('owner', $schema['relationships'][0]['name']);
        $this->assertEquals('belongsTo', $schema['relationships'][0]['type']);
        $this->assertEquals(User::class, $schema['relationships'][0]['model']);
        $this->assertEquals('owner_id', $schema['relationships'][0]['foreignKey']);
    }

    public function test_base_schema_inspector_validates_rlm_pattern(): void
    {
        $inspector = new BaseSchemaInspector(RlmPattern::class);
        $inspector->validate();

        $this->assertNotEmpty($inspector->columns());
        $this->assertNotEmpty($inspector->traits());
        $this->assertNotEmpty($inspector->contracts());
        $this->assertNotEmpty($inspector->fillable());
        $this->assertNotEmpty($inspector->casts());
        $this->assertNotEmpty($inspector->relationships());
    }

    public function test_base_schema_inspector_finds_columns_by_name(): void
    {
        $inspector = new BaseSchemaInspector(RlmPattern::class);
        $inspector->validate();

        $this->assertTrue($inspector->hasColumn('name'));
        $this->assertTrue($inspector->hasColumn('check_regex'));
        $this->assertFalse($inspector->hasColumn('nonexistent'));
    }

    public function test_base_schema_inspector_returns_column_types(): void
    {
        $inspector = new BaseSchemaInspector(RlmPattern::class);
        $inspector->validate();

        $this->assertEquals('string', $inspector->columnType('name'));
        $this->assertEquals('text', $inspector->columnType('description'));
        $this->assertEquals('boolean', $inspector->columnType('is_active'));
        $this->assertEquals('decimal', $inspector->columnType('weight'));
        $this->assertNull($inspector->columnType('nonexistent'));
    }
}
