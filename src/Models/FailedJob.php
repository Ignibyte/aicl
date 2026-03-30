<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\FailedJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Model for Laravel's built-in failed_jobs table.
 *
 * @property int    $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property Carbon $failed_at
 */
class FailedJob extends Model
{
    /** @use HasFactory<FailedJobFactory> */
    use HasFactory;

    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function getJobNameAttribute(): string
    {
        $payload = $this->payload;

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        return $payload['displayName'] ?? $payload['job'] ?? 'Unknown Job';
    }

    public function getExceptionSummaryAttribute(): string
    {
        $lines = explode("\n", $this->exception);

        return $lines[0] ?? 'No exception message';
    }

    protected static function newFactory(): FailedJobFactory
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return FailedJobFactory::new();
        // @codeCoverageIgnoreEnd
    }
}
