<?php

namespace Aicl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasUuids;
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'query',
        'user_id',
        'entity_type_filter',
        'results_count',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) config('aicl.search.analytics.retention_days', 90);

        return static::query()->where('searched_at', '<', now()->subDays($retentionDays));
    }
}
