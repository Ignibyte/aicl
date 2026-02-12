<?php

namespace Aicl\Rlm\Embeddings;

/**
 * Elasticsearch index mappings for RLM models with dense_vector fields.
 *
 * All indices use the `aicl_` prefix to avoid collision with client Scout indices.
 * Each index includes a `dense_vector` field for kNN similarity search alongside
 * standard BM25 text fields.
 */
class IndexMappings
{
    private const VECTOR_DIMENSION = 1536;

    /**
     * Get all index mappings keyed by index name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'aicl_rlm_failures' => self::rlmFailures(),
            'aicl_rlm_lessons' => self::rlmLessons(),
            'aicl_rlm_patterns' => self::rlmPatterns(),
            'aicl_prevention_rules' => self::preventionRules(),
            'aicl_golden_annotations' => self::goldenAnnotations(),
        ];
    }

    /**
     * Get the index names for all RLM models.
     *
     * @return array<int, string>
     */
    public static function indexNames(): array
    {
        return array_keys(self::all());
    }

    /**
     * Get mapping for a specific index.
     *
     * @return array<string, mixed>|null
     */
    public static function forIndex(string $indexName): ?array
    {
        return self::all()[$indexName] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rlmFailures(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'failure_code' => ['type' => 'keyword'],
                    'category' => ['type' => 'keyword'],
                    'severity' => ['type' => 'keyword'],
                    'status' => ['type' => 'keyword'],
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'root_cause' => ['type' => 'text', 'analyzer' => 'standard'],
                    'preventive_rule' => ['type' => 'text', 'analyzer' => 'standard'],
                    'fix' => ['type' => 'text', 'analyzer' => 'standard'],
                    'scaffolding_fixed' => ['type' => 'boolean'],
                    'promoted_to_base' => ['type' => 'boolean'],
                    'report_count' => ['type' => 'integer'],
                    'project_count' => ['type' => 'integer'],
                    'is_active' => ['type' => 'boolean'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => self::VECTOR_DIMENSION,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rlmLessons(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'topic' => ['type' => 'text', 'analyzer' => 'standard', 'fields' => ['keyword' => ['type' => 'keyword']]],
                    'subtopic' => ['type' => 'text', 'analyzer' => 'standard'],
                    'summary' => ['type' => 'text', 'analyzer' => 'standard'],
                    'detail' => ['type' => 'text', 'analyzer' => 'standard'],
                    'tags' => ['type' => 'text', 'analyzer' => 'standard'],
                    'confidence' => ['type' => 'float'],
                    'is_verified' => ['type' => 'boolean'],
                    'is_active' => ['type' => 'boolean'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => self::VECTOR_DIMENSION,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rlmPatterns(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'name' => ['type' => 'text', 'analyzer' => 'standard', 'fields' => ['keyword' => ['type' => 'keyword']]],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'target' => ['type' => 'text', 'analyzer' => 'standard', 'fields' => ['keyword' => ['type' => 'keyword']]],
                    'category' => ['type' => 'keyword'],
                    'severity' => ['type' => 'keyword'],
                    'weight' => ['type' => 'float'],
                    'is_active' => ['type' => 'boolean'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => self::VECTOR_DIMENSION,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function preventionRules(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'rule_text' => ['type' => 'text', 'analyzer' => 'standard'],
                    'trigger_context' => ['type' => 'text', 'analyzer' => 'standard'],
                    'confidence' => ['type' => 'float'],
                    'priority' => ['type' => 'integer'],
                    'is_active' => ['type' => 'boolean'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => self::VECTOR_DIMENSION,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function goldenAnnotations(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'annotation_key' => ['type' => 'keyword'],
                    'annotation_text' => ['type' => 'text', 'analyzer' => 'standard'],
                    'rationale' => ['type' => 'text', 'analyzer' => 'standard'],
                    'category' => ['type' => 'keyword'],
                    'pattern_name' => ['type' => 'keyword'],
                    'feature_tags' => ['type' => 'keyword'],
                    'is_active' => ['type' => 'boolean'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => self::VECTOR_DIMENSION,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ];
    }
}
