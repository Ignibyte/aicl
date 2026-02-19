<?php

namespace Aicl\Models;

use Aicl\Database\Factories\EntityWaiverFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id UUID primary key
 * @property string $entity_name Entity this waiver applies to
 * @property string $pattern_id Pattern being waived
 * @property string $reason Human-readable justification
 * @property string $scope_justification Why this exception is scoped
 * @property string|null $ticket_url Tracking reference
 * @property \Carbon\Carbon|null $expires_at Auto-revert after this date
 * @property int $created_by User who created the waiver
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read User $creator
 */
class EntityWaiver extends Model
{
    /** @use HasFactory<EntityWaiverFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_name',
        'pattern_id',
        'reason',
        'scope_justification',
        'ticket_url',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Scope to active (non-expired) waivers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to waivers for a specific entity.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEntity($query, string $entityName)
    {
        return $query->where('entity_name', $entityName);
    }

    protected static function newFactory(): EntityWaiverFactory
    {
        return EntityWaiverFactory::new();
    }
}
