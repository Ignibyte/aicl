<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntitySignature;
use PHPUnit\Framework\TestCase;

class EntitySignatureTest extends TestCase
{
    public function test_basic_construction(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'status' => 'enum:TaskStatus'],
            states: ['draft', 'active', 'completed'],
            relationships: ['belongsTo:User:owner'],
            features: ['media', 'pdf'],
        );

        $this->assertSame('Task', $sig->entityName);
        $this->assertSame(['name' => 'string', 'status' => 'enum:TaskStatus'], $sig->fields);
        $this->assertSame(['draft', 'active', 'completed'], $sig->states);
        $this->assertSame(['belongsTo:User:owner'], $sig->relationships);
        $this->assertSame(['media', 'pdf'], $sig->features);
    }

    public function test_defaults_to_empty_arrays(): void
    {
        $sig = new EntitySignature(entityName: 'Simple');

        $this->assertSame([], $sig->fields);
        $this->assertSame([], $sig->states);
        $this->assertSame([], $sig->relationships);
        $this->assertSame([], $sig->features);
    }

    public function test_expected_files_base_set(): void
    {
        $sig = new EntitySignature(entityName: 'Task');

        $files = $sig->expectedFiles();

        $this->assertContains('model', $files);
        $this->assertContains('migration', $files);
        $this->assertContains('factory', $files);
        $this->assertContains('policy', $files);
        $this->assertContains('observer', $files);
        $this->assertContains('filament', $files);
        $this->assertContains('form', $files);
        $this->assertContains('infolist', $files);
        $this->assertContains('test', $files);
        $this->assertNotContains('blade_view', $files);
        $this->assertNotContains('view_controller', $files);
    }

    public function test_expected_files_with_views_feature(): void
    {
        $sig = new EntitySignature(
            entityName: 'Article',
            features: ['views'],
        );

        $files = $sig->expectedFiles();

        $this->assertContains('blade_view', $files);
        $this->assertContains('view_controller', $files);
    }

    public function test_hash_is_deterministic(): void
    {
        $args = [
            'entityName' => 'Task',
            'fields' => ['name' => 'string', 'status' => 'enum:TaskStatus'],
            'states' => ['draft', 'active'],
            'relationships' => ['belongsTo:User:owner'],
            'features' => ['media'],
        ];

        $sig1 = new EntitySignature(...$args);
        $sig2 = new EntitySignature(...$args);

        $this->assertSame($sig1->hash(), $sig2->hash());
        $this->assertSame(64, strlen($sig1->hash())); // SHA-256 hex
    }

    public function test_hash_is_order_independent(): void
    {
        $sig1 = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'status' => 'enum:TaskStatus'],
            states: ['active', 'draft'],
            relationships: ['hasMany:Comment', 'belongsTo:User:owner'],
            features: ['pdf', 'media'],
        );

        $sig2 = new EntitySignature(
            entityName: 'Task',
            fields: ['status' => 'enum:TaskStatus', 'name' => 'string'],
            states: ['draft', 'active'],
            relationships: ['belongsTo:User:owner', 'hasMany:Comment'],
            features: ['media', 'pdf'],
        );

        $this->assertSame($sig1->hash(), $sig2->hash());
    }

    public function test_different_entities_produce_different_hashes(): void
    {
        $sig1 = new EntitySignature(entityName: 'Task');
        $sig2 = new EntitySignature(entityName: 'Project');

        $this->assertNotSame($sig1->hash(), $sig2->hash());
    }

    public function test_to_array_roundtrip(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'priority' => 'integer'],
            states: ['open', 'closed'],
            relationships: ['belongsTo:User:owner', 'hasMany:Comment'],
            features: ['media', 'notifications'],
        );

        $array = $sig->toArray();
        $restored = EntitySignature::fromArray($array);

        $this->assertSame($sig->entityName, $restored->entityName);
        $this->assertSame($sig->fields, $restored->fields);
        $this->assertSame($sig->states, $restored->states);
        $this->assertSame($sig->relationships, $restored->relationships);
        $this->assertSame($sig->features, $restored->features);
        $this->assertSame($sig->hash(), $restored->hash());
    }

    public function test_from_array_handles_missing_keys(): void
    {
        $sig = EntitySignature::fromArray(['entity_name' => 'Minimal']);

        $this->assertSame('Minimal', $sig->entityName);
        $this->assertSame([], $sig->fields);
        $this->assertSame([], $sig->states);
        $this->assertSame([], $sig->relationships);
        $this->assertSame([], $sig->features);
    }

    public function test_from_array_handles_empty_array(): void
    {
        $sig = EntitySignature::fromArray([]);

        $this->assertSame('', $sig->entityName);
    }

    public function test_to_context_basic(): void
    {
        $sig = new EntitySignature(entityName: 'Task');

        $ctx = $sig->toContext();

        $this->assertFalse($ctx['has_states']);
        $this->assertFalse($ctx['has_media']);
        $this->assertFalse($ctx['has_enum']);
        $this->assertFalse($ctx['has_pdf']);
        $this->assertFalse($ctx['has_notifications']);
        $this->assertFalse($ctx['has_widgets']);
        $this->assertFalse($ctx['has_views']);
        $this->assertTrue($ctx['has_search']);
        $this->assertTrue($ctx['has_audit']);
        $this->assertTrue($ctx['has_api']);
    }

    public function test_to_context_with_features(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['status' => 'enum:TaskStatus'],
            states: ['draft', 'active'],
            features: ['media', 'pdf', 'notifications', 'widgets', 'views'],
        );

        $ctx = $sig->toContext();

        $this->assertTrue($ctx['has_states']);
        $this->assertTrue($ctx['has_media']);
        $this->assertTrue($ctx['has_enum']);
        $this->assertTrue($ctx['has_pdf']);
        $this->assertTrue($ctx['has_notifications']);
        $this->assertTrue($ctx['has_widgets']);
        $this->assertTrue($ctx['has_views']);
    }

    public function test_to_context_detects_enum_prefix(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'priority' => 'enum:Priority'],
        );

        $this->assertTrue($sig->toContext()['has_enum']);
    }

    public function test_to_context_detects_bare_enum_type(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'category' => 'enum'],
        );

        $this->assertTrue($sig->toContext()['has_enum']);
    }

    public function test_to_context_no_enum_without_enum_fields(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string', 'count' => 'integer'],
        );

        $this->assertFalse($sig->toContext()['has_enum']);
    }

    public function test_to_array_contains_all_keys(): void
    {
        $sig = new EntitySignature(
            entityName: 'Task',
            fields: ['name' => 'string'],
            states: ['draft'],
            relationships: ['belongsTo:User:owner'],
            features: ['media'],
        );

        $array = $sig->toArray();

        $this->assertArrayHasKey('entity_name', $array);
        $this->assertArrayHasKey('fields', $array);
        $this->assertArrayHasKey('states', $array);
        $this->assertArrayHasKey('relationships', $array);
        $this->assertArrayHasKey('features', $array);
        $this->assertCount(5, $array);
    }
}
