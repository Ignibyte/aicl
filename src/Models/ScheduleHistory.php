<?php

namespace Aicl\Models;

use Aicl\Database\Factories\ScheduleHistoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $command
 * @property string|null $description
 * @property string $expression
 * @property string $status
 * @property int|null $exit_code
 * @property string|null $output
 * @property int|null $duration_ms
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class ScheduleHistory extends Model
{
    /** @use HasFactory<ScheduleHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'schedule_history';

    protected $fillable = [
        'command',
        'description',
        'expression',
        'status',
        'exit_code',
        'output',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    /**
     * @param  Builder<ScheduleHistory>  $query
     * @return Builder<ScheduleHistory>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * @param  Builder<ScheduleHistory>  $query
     * @return Builder<ScheduleHistory>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * @param  Builder<ScheduleHistory>  $query
     * @return Builder<ScheduleHistory>
     */
    public function scopeForCommand(Builder $query, string $command): Builder
    {
        return $query->where('command', $command);
    }

    /**
     * @param  Builder<ScheduleHistory>  $query
     * @return Builder<ScheduleHistory>
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('started_at', '>=', now()->subHours($hours));
    }

    protected static function newFactory(): ScheduleHistoryFactory
    {
        return ScheduleHistoryFactory::new();
    }
}
