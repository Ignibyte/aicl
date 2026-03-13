<?php

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchResult;
use Tests\TestCase;

class SearchResultTest extends TestCase
{
    public function test_from_es_hit_creates_result(): void
    {
        $hit = [
            '_score' => 5.5,
            '_source' => [
                'entity_type' => 'App\\Models\\User',
                'entity_id' => 'uuid-123',
                'title' => 'John Doe',
                'body' => 'john@example.com',
                'url' => '/admin/users/uuid-123',
                'icon' => 'heroicon-o-user',
                'meta' => ['role' => 'admin'],
            ],
        ];

        $result = SearchResult::fromEsHit($hit);

        $this->assertSame('App\\Models\\User', $result->entityType);
        $this->assertSame('uuid-123', $result->entityId);
        $this->assertSame('John Doe', $result->title);
        $this->assertSame('john@example.com', $result->subtitle);
        $this->assertSame('/admin/users/uuid-123', $result->url);
        $this->assertSame('heroicon-o-user', $result->icon);
        $this->assertSame(5.5, $result->score);
        $this->assertSame(['role' => 'admin'], $result->meta);
    }

    public function test_from_es_hit_handles_missing_fields(): void
    {
        $hit = [
            '_source' => [
                'entity_type' => 'App\\Models\\Task',
                'entity_id' => '1',
                'title' => 'Test Task',
            ],
        ];

        $result = SearchResult::fromEsHit($hit);

        $this->assertSame('App\\Models\\Task', $result->entityType);
        $this->assertNull($result->subtitle);
        $this->assertSame('', $result->url);
        $this->assertSame('heroicon-o-document', $result->icon);
        $this->assertSame(0.0, $result->score);
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $result = new SearchResult(
            entityType: 'App\\Models\\User',
            entityId: '1',
            title: 'John',
            subtitle: 'john@example.com',
            url: '/admin/users/1',
            icon: 'heroicon-o-user',
            score: 3.2,
        );

        $array = $result->toArray();

        $this->assertSame('App\\Models\\User', $array['entity_type']);
        $this->assertSame('John', $array['title']);
        $this->assertSame('User', $array['type']);
        $this->assertSame('heroicon-o-user', $array['type_icon']);
        $this->assertSame('primary', $array['type_color']);
    }
}
