<?php

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchDocumentBuilder;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class SearchDocumentBuilderTest extends TestCase
{
    protected SearchDocumentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SearchDocumentBuilder;
    }

    public function test_build_creates_document_with_expected_fields(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';

            protected $guarded = [];

            public $incrementing = false;
        };

        $model->forceFill([
            'id' => 'uuid-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $config = [
            'fields' => ['name', 'email'],
            'label' => 'name',
            'subtitle' => 'email',
            'icon' => 'heroicon-o-user',
            'boost' => 1.5,
            'visibility' => 'role:admin',
        ];

        $document = $this->builder->build($model, $config);

        $this->assertSame('John Doe', $document['title']);
        $this->assertStringContainsString('John Doe', $document['body']);
        $this->assertStringContainsString('john@example.com', $document['body']);
        $this->assertSame('heroicon-o-user', $document['icon']);
        $this->assertSame(1.5, $document['boost']);
        $this->assertSame('role:admin', $document['required_permission']);
        $this->assertArrayHasKey('indexed_at', $document);
    }

    public function test_document_id_is_deterministic(): void
    {
        $model = new class extends Model
        {
            protected $table = 'users';

            protected $guarded = [];

            public $incrementing = false;
        };

        $model->forceFill(['id' => 'abc-123']);

        $id1 = $this->builder->documentId($model);
        $id2 = $this->builder->documentId($model);

        $this->assertSame($id1, $id2);
        $this->assertStringContainsString('abc-123', $id1);
    }

    public function test_build_handles_null_fields(): void
    {
        $model = new class extends Model
        {
            protected $table = 'tasks';

            protected $guarded = [];

            public $incrementing = false;
        };

        $model->forceFill([
            'id' => '1',
            'title' => 'Test',
            'description' => null,
        ]);

        $config = [
            'fields' => ['title', 'description'],
            'label' => 'title',
        ];

        $document = $this->builder->build($model, $config);

        $this->assertSame('Test', $document['title']);
        $this->assertSame('Test', $document['body']); // null description excluded
    }

    public function test_build_resolves_owner_id(): void
    {
        $model = new class extends Model
        {
            protected $table = 'tasks';

            protected $guarded = [];

            public $incrementing = false;
        };

        $model->forceFill([
            'id' => '1',
            'name' => 'Task',
            'owner_id' => 42,
        ]);

        $config = [
            'fields' => ['name'],
            'label' => 'name',
            'visibility' => 'owner+admin',
        ];

        $document = $this->builder->build($model, $config);

        $this->assertSame('42', $document['owner_id']);
    }
}
