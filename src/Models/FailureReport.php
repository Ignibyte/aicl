<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\FailureReportFactory;
use Aicl\Enums\ResolutionMethod;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $rlm_failure_id
 * @property string $project_hash
 * @property string $entity_name
 * @property array|null $scaffolder_args
 * @property string|null $phase
 * @property string|null $agent
 * @property bool $resolved
 * @property string|null $resolution_notes
 * @property ResolutionMethod|null $resolution_method
 * @property int|null $time_to_resolve
 * @property \Carbon\Carbon $reported_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property bool $is_active
 * @property int $owner_id
 *
 * @method static Builder<static> resolved()
 * @method static Builder<static> unresolved()
 * @method static Builder<static> byProject(string $hash)
 * @method static Builder<static> byPhase(string $phase)
 * @method static Builder<static> byAgent(string $agent)
 */
class FailureReport extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEntityEvents;

    /** @use HasFactory<FailureReportFactory> */
    use HasFactory;

    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rlm_failure_id',
        'project_hash',
        'entity_name',
        'scaffolder_args',
        'phase',
        'agent',
        'resolved',
        'resolution_notes',
        'resolution_method',
        'time_to_resolve',
        'reported_at',
        'resolved_at',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'scaffolder_args' => 'array',
            'resolved' => 'boolean',
            'resolution_method' => ResolutionMethod::class,
            'time_to_resolve' => 'integer',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RlmFailure, $this>
     */
    public function failure(): BelongsTo
    {
        return $this->belongsTo(RlmFailure::class, 'rlm_failure_id');
    }

    /**
     * @return BelongsTo<RlmFailure, $this>
     */
    public function rlmFailure(): BelongsTo
    {
        return $this->failure();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('resolved', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByProject(Builder $query, string $hash): Builder
    {
        return $query->where('project_hash', $hash);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByPhase(Builder $query, string $phase): Builder
    {
        return $query->where('phase', $phase);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByAgent(Builder $query, string $agent): Builder
    {
        return $query->where('agent', $agent);
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['entity_name', 'project_hash', 'phase', 'agent'];
    }

    protected static function newFactory(): FailureReportFactory
    {
        return FailureReportFactory::new();
    }
}
