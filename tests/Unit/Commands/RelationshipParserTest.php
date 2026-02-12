<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Support\RelationshipDefinition;
use Aicl\Console\Support\RelationshipParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RelationshipParserTest extends TestCase
{
    protected RelationshipParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new RelationshipParser;
    }

    // ========================================================================
    // Basic Parsing
    // ========================================================================

    public function test_parses_simple_has_many(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertCount(1, $rels);
        $this->assertEquals('tasks', $rels[0]->name);
        $this->assertEquals('hasMany', $rels[0]->type);
        $this->assertEquals('Task', $rels[0]->relatedModel);
        $this->assertNull($rels[0]->foreignKey);
    }

    public function test_parses_multiple_relationships(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task,comments:hasMany:Comment');

        $this->assertCount(2, $rels);
        $this->assertEquals('tasks', $rels[0]->name);
        $this->assertEquals('comments', $rels[1]->name);
    }

    public function test_returns_empty_array_for_empty_string(): void
    {
        $rels = $this->parser->parse('');

        $this->assertCount(0, $rels);
    }

    public function test_returns_empty_array_for_whitespace(): void
    {
        $rels = $this->parser->parse('   ');

        $this->assertCount(0, $rels);
    }

    public function test_trims_whitespace(): void
    {
        $rels = $this->parser->parse(' tasks:hasMany:Task , comments:hasMany:Comment ');

        $this->assertCount(2, $rels);
    }

    public function test_skips_empty_segments(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task,');

        $this->assertCount(1, $rels);
    }

    // ========================================================================
    // All 4 Relationship Types
    // ========================================================================

    public function test_parses_has_many_type(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertEquals('hasMany', $rels[0]->type);
    }

    public function test_parses_has_one_type(): void
    {
        $rels = $this->parser->parse('profile:hasOne:Profile');

        $this->assertEquals('hasOne', $rels[0]->type);
    }

    public function test_parses_belongs_to_many_type(): void
    {
        $rels = $this->parser->parse('tags:belongsToMany:Tag');

        $this->assertEquals('belongsToMany', $rels[0]->type);
    }

    public function test_parses_morph_many_type(): void
    {
        $rels = $this->parser->parse('comments:morphMany:Comment');

        $this->assertEquals('morphMany', $rels[0]->type);
    }

    // ========================================================================
    // Optional Foreign Key
    // ========================================================================

    public function test_parses_optional_foreign_key(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task:project_id');

        $this->assertEquals('project_id', $rels[0]->foreignKey);
    }

    public function test_foreign_key_is_null_when_not_provided(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertNull($rels[0]->foreignKey);
    }

    // ========================================================================
    // RelationshipDefinition Helper Methods
    // ========================================================================

    public function test_eloquent_type_has_many(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertEquals('HasMany', $rels[0]->eloquentType());
    }

    public function test_eloquent_type_has_one(): void
    {
        $rels = $this->parser->parse('profile:hasOne:Profile');

        $this->assertEquals('HasOne', $rels[0]->eloquentType());
    }

    public function test_eloquent_type_belongs_to_many(): void
    {
        $rels = $this->parser->parse('tags:belongsToMany:Tag');

        $this->assertEquals('BelongsToMany', $rels[0]->eloquentType());
    }

    public function test_eloquent_type_morph_many(): void
    {
        $rels = $this->parser->parse('comments:morphMany:Comment');

        $this->assertEquals('MorphMany', $rels[0]->eloquentType());
    }

    public function test_eloquent_import_has_many(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertEquals(
            'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            $rels[0]->eloquentImport()
        );
    }

    public function test_eloquent_import_belongs_to_many(): void
    {
        $rels = $this->parser->parse('tags:belongsToMany:Tag');

        $this->assertEquals(
            'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany',
            $rels[0]->eloquentImport()
        );
    }

    // ========================================================================
    // Validation Errors
    // ========================================================================

    public function test_rejects_missing_model(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected format');

        $this->parser->parse('tasks:hasMany');
    }

    public function test_rejects_missing_type_and_model(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected format');

        $this->parser->parse('tasks');
    }

    public function test_rejects_non_camel_case_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('camelCase');

        $this->parser->parse('my_tasks:hasMany:Task');
    }

    public function test_rejects_name_starting_with_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('camelCase');

        $this->parser->parse('Tasks:hasMany:Task');
    }

    public function test_rejects_duplicate_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate');

        $this->parser->parse('tasks:hasMany:Task,tasks:hasOne:Task');
    }

    public function test_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown relationship type');

        $this->parser->parse('tasks:belongsTo:Task');
    }

    public function test_rejects_non_pascal_case_model(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PascalCase');

        $this->parser->parse('tasks:hasMany:task');
    }

    public function test_collects_multiple_errors(): void
    {
        try {
            $this->parser->parse('my_tasks:belongsTo:task');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('camelCase', $message);
        }
    }

    // ========================================================================
    // Returns RelationshipDefinition Instances
    // ========================================================================

    public function test_returns_relationship_definition_instances(): void
    {
        $rels = $this->parser->parse('tasks:hasMany:Task');

        $this->assertInstanceOf(RelationshipDefinition::class, $rels[0]);
    }
}
