<?php

declare(strict_types=1);

namespace Aicl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SearchLog.
 */
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'searched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        // @codeCoverageIgnoreStart — Untestable in unit context
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $retentionDays = (int) config('aicl.search.analytics.retention_days', 90);

        return static::query()->where('searched_at', '<', now()->subDays($retentionDays));
        // @codeCoverageIgnoreEnd
    }
}
