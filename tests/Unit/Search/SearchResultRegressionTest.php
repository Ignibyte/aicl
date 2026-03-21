<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchResult;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SearchResult PHPStan changes.
 *
 * Covers the (float) cast on _score in fromEsHit(), null coalescing
 * for missing source fields, toArray() return type annotation, and
 * the readonly constructor properties. Extends existing SearchResultTest
 * with PHPStan-specific edge cases.
 */
class SearchResultRegressionTest extends TestCase
{
    /**
     * Test fromEsHit handles completely empty hit.
     *
     * PHPStan enforced null coalescing: $hit['_source'] ?? [].
     * When _source is missing, all fields fall back to defaults.
     */
    public function test_from_es_hit_handles_empty_hit(): void
    {
        // Arrange: hit with no _source at all
        $hit = [];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert: all fields use defaults
        $this->assertSame('', $result->entityType);
        $this->assertSame('', $result->entityId);
        $this->assertSame('', $result->title);
        $this->assertNull($result->subtitle);
        $this->assertSame('', $result->url);
        $this->assertSame('heroicon-o-document', $result->icon);
        $this->assertSame(0.0, $result->score);
        $this->assertSame([], $result->meta);
    }

    /**
     * Test fromEsHit casts score to float.
     *
     * PHPStan enforced (float) cast: (float) ($hit['_score'] ?? 0.0).
     */
    public function test_from_es_hit_casts_score_to_float(): void
    {
        // Arrange: score as integer
        $hit = [
            '_score' => 5,
            '_source' => ['entity_type' => 'User', 'entity_id' => '1', 'title' => 'Test'],
        ];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert: integer 5 cast to float 5.0
        $this->assertSame(5.0, $result->score);
    }

    /**
     * Test fromEsHit handles null score.
     *
     * When _score is null (e.g., filter-only queries), falls back to 0.0.
     */
    public function test_from_es_hit_handles_null_score(): void
    {
        // Arrange
        $hit = [
            '_score' => null,
            '_source' => ['entity_type' => 'User', 'entity_id' => '1', 'title' => 'Test'],
        ];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert
        $this->assertSame(0.0, $result->score);
    }

    /**
     * Test fromEsHit uses default icon when missing.
     *
     * Null coalescing: $source['icon'] ?? 'heroicon-o-document'.
     */
    public function test_from_es_hit_uses_default_icon(): void
    {
        // Arrange: no icon in source
        $hit = [
            '_score' => 1.0,
            '_source' => ['entity_type' => 'App\\Models\\Task', 'entity_id' => '1', 'title' => 'Task'],
        ];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert
        $this->assertSame('heroicon-o-document', $result->icon);
    }

    /**
     * Test fromEsHit uses provided icon.
     */
    public function test_from_es_hit_uses_provided_icon(): void
    {
        // Arrange
        $hit = [
            '_score' => 1.0,
            '_source' => [
                'entity_type' => 'User',
                'entity_id' => '1',
                'title' => 'Test',
                'icon' => 'heroicon-o-user',
            ],
        ];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert
        $this->assertSame('heroicon-o-user', $result->icon);
    }

    /**
     * Test fromEsHit handles meta as empty array when missing.
     *
     * Null coalescing: $source['meta'] ?? [].
     */
    public function test_from_es_hit_defaults_meta_to_empty_array(): void
    {
        // Arrange: no meta in source
        $hit = [
            '_score' => 1.0,
            '_source' => ['entity_type' => 'User', 'entity_id' => '1', 'title' => 'Test'],
        ];

        // Act
        $result = SearchResult::fromEsHit($hit);

        // Assert
        $this->assertSame([], $result->meta);
    }

    /**
     * Test toArray includes type derived from class_basename.
     *
     * toArray() uses class_basename($this->entityType) for the 'type' key.
     */
    public function test_to_array_type_uses_class_basename(): void
    {
        // Arrange
        $result = new SearchResult(
            entityType: 'App\\Models\\User',
            entityId: '1',
            title: 'Test',
            subtitle: null,
            url: '/admin/users/1',
            icon: 'heroicon-o-user',
            score: 1.0,
        );

        // Act
        $array = $result->toArray();

        // Assert: 'type' key is the basename of the entity class
        $this->assertSame('User', $array['type']);
    }

    /**
     * Test toArray includes all expected keys.
     *
     * PHPStan added @return array<string, mixed> annotation.
     */
    public function test_to_array_includes_all_keys(): void
    {
        // Arrange
        $result = new SearchResult(
            entityType: 'App\\Models\\Project',
            entityId: 'uuid-abc',
            title: 'My Project',
            subtitle: 'A description',
            url: '/admin/projects/uuid-abc',
            icon: 'heroicon-o-folder',
            score: 3.5,
            meta: ['status' => 'active'],
        );

        // Act
        $array = $result->toArray();

        // Assert: verify all keys exist
        $expectedKeys = [
            'entity_type', 'entity_id', 'title', 'subtitle',
            'url', 'icon', 'score', 'meta', 'type', 'type_icon', 'type_color',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: {$key}");
        }
    }

    /**
     * Test toArray type_color is always 'primary'.
     */
    public function test_to_array_type_color_is_primary(): void
    {
        // Arrange
        $result = new SearchResult('Type', 'id', 'Title', null, '/url', 'icon', 1.0);

        // Act
        $array = $result->toArray();

        // Assert
        $this->assertSame('primary', $array['type_color']);
    }

    /**
     * Test toArray type_icon matches the result icon.
     */
    public function test_to_array_type_icon_matches_icon(): void
    {
        // Arrange
        $result = new SearchResult('Type', 'id', 'Title', null, '/url', 'heroicon-o-star', 1.0);

        // Act
        $array = $result->toArray();

        // Assert
        $this->assertSame('heroicon-o-star', $array['type_icon']);
    }

    /**
     * Test constructor readonly properties.
     */
    public function test_constructor_properties_are_readonly(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(SearchResult::class);

        // Assert: all constructor properties should be readonly
        $readonlyProps = ['entityType', 'entityId', 'title', 'subtitle', 'url', 'icon', 'score', 'meta'];
        foreach ($readonlyProps as $prop) {
            $property = $reflection->getProperty($prop);
            $this->assertTrue($property->isReadOnly(), "Property {$prop} should be readonly");
        }
    }
}
