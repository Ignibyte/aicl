<?php

namespace Aicl\Horizon\Models;

use Aicl\Database\Factories\Horizon\QueueMetricSnapshotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $name
 * @property float $throughput
 * @property float $runtime
 * @property float|null $wait
 * @property Carbon $recorded_at
 */
class QueueMetricSnapshot extends Model
{
    /** @use HasFactory<QueueMetricSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'queue_metric_snapshots';

    protected $fillable = [
        'type',
        'name',
        'throughput',
        'runtime',
        'wait',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'throughput' => 'float',
            'runtime' => 'float',
            'wait' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Scope to filter by type (queue or job).
     *
     * @param  Builder<QueueMetricSnapshot>  $query
     * @return Builder<QueueMetricSnapshot>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter for a specific queue.
     *
     * @param  Builder<QueueMetricSnapshot>  $query
     * @return Builder<QueueMetricSnapshot>
     */
    public function scopeForQueue(Builder $query, string $name): Builder
    {
        return $query->where('type', 'queue')->where('name', $name);
    }

    /**
     * Scope to filter for a specific job.
     *
     * @param  Builder<QueueMetricSnapshot>  $query
     * @return Builder<QueueMetricSnapshot>
     */
    public function scopeForJob(Builder $query, string $name): Builder
    {
        return $query->where('type', 'job')->where('name', $name);
    }

    /**
     * Scope to filter by time range (minutes back from now).
     *
     * @param  Builder<QueueMetricSnapshot>  $query
     * @return Builder<QueueMetricSnapshot>
     */
    public function scopeForRange(Builder $query, int $minutesBack): Builder
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutesBack));
    }

    /**
     * Get historical data for a specific type and name within a time range.
     *
     * @return Collection<int, QueueMetricSnapshot>
     */
    public static function getHistoricalData(string $type, string $name, int $minutesBack): Collection
    {
        return static::query()
            ->ofType($type)
            ->where('name', $name)
            ->forRange($minutesBack)
            ->orderBy('recorded_at')
            ->get();
    }

    protected static function newFactory(): QueueMetricSnapshotFactory
    {
        return QueueMetricSnapshotFactory::new();
    }
}
