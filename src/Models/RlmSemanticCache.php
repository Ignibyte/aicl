<?php

namespace Aicl\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $cache_key
 * @property string $check_name
 * @property string $entity_name
 * @property bool $passed
 * @property string $message
 * @property float $confidence
 * @property string $files_hash
 * @property \Carbon\Carbon $expires_at
 */
class RlmSemanticCache extends Model
{
    use HasUuids;

    protected $table = 'rlm_semantic_cache';

    protected $fillable = [
        'cache_key',
        'check_name',
        'entity_name',
        'passed',
        'message',
        'confidence',
        'files_hash',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'confidence' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }
}
