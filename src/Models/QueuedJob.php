<?php

namespace Aicl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Laravel's built-in jobs table (database queue driver).
 *
 * @property int $id
 * @property string $queue
 * @property string $payload
 * @property int $attempts
 * @property int|null $reserved_at
 * @property int $available_at
 * @property int $created_at
 */
class QueuedJob extends Model
{
    protected $table = 'jobs';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reserved_at' => 'integer',
            'available_at' => 'integer',
            'created_at' => 'integer',
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

    public function getAvailableAtDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->available_at
            ? \Illuminate\Support\Carbon::createFromTimestamp($this->available_at)
            : null;
    }

    public function getCreatedAtDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->created_at
            ? \Illuminate\Support\Carbon::createFromTimestamp($this->created_at)
            : null;
    }

    public function getReservedAtDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->reserved_at
            ? \Illuminate\Support\Carbon::createFromTimestamp($this->reserved_at)
            : null;
    }

    public function getIsReservedAttribute(): bool
    {
        return $this->reserved_at !== null;
    }
}
