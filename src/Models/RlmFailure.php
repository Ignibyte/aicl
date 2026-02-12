<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\RlmFailureFactory;
use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\States\RlmFailureState;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEmbeddings;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasSearchableFields;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $failure_code
 * @property string|null $pattern_id
 * @property FailureCategory $category
 * @property string|null $subcategory
 * @property string $title
 * @property string $description
 * @property string|null $root_cause
 * @property string|null $fix
 * @property string|null $preventive_rule
 * @property FailureSeverity $severity
 * @property array|null $entity_context
 * @property bool $scaffolding_fixed
 * @property \Carbon\Carbon|null $first_seen_at
 * @property \Carbon\Carbon|null $last_seen_at
 * @property int $report_count
 * @property int $project_count
 * @property int $resolution_count
 * @property float|null $resolution_rate
 * @property bool $promoted_to_base
 * @property \Carbon\Carbon|null $promoted_at
 * @property string|null $aicl_version
 * @property string|null $laravel_version
 * @property RlmFailureState $status
 * @property bool $is_active
 * @property int $owner_id
 * @property-read User $owner
 * @property-read float $computed_resolution_rate
 */
class RlmFailure extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEmbeddings;
    use HasEntityEvents;

    /** @use HasFactory<RlmFailureFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasStandardScopes;
    use HasStates;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'failure_code',
        'pattern_id',
        'category',
        'subcategory',
        'title',
        'description',
        'root_cause',
        'fix',
        'preventive_rule',
        'severity',
        'entity_context',
        'scaffolding_fixed',
        'first_seen_at',
        'last_seen_at',
        'report_count',
        'project_count',
        'resolution_count',
        'resolution_rate',
        'promoted_to_base',
        'promoted_at',
        'aicl_version',
        'laravel_version',
        'status',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => FailureCategory::class,
            'severity' => FailureSeverity::class,
            'entity_context' => 'array',
            'scaffolding_fixed' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'report_count' => 'integer',
            'project_count' => 'integer',
            'resolution_count' => 'integer',
            'resolution_rate' => 'decimal:2',
            'promoted_to_base' => 'boolean',
            'promoted_at' => 'datetime',
            'status' => RlmFailureState::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<FailureReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(FailureReport::class, 'rlm_failure_id');
    }

    /**
     * @return HasMany<PreventionRule, $this>
     */
    public function preventionRules(): HasMany
    {
        return $this->hasMany(PreventionRule::class, 'rlm_failure_id');
    }

    /**
     * Computed resolution rate: resolution_count / max(report_count, 1).
     */
    public function getComputedResolutionRateAttribute(): float
    {
        return $this->report_count > 0
            ? round($this->resolution_count / $this->report_count, 3)
            : 0.0;
    }

    /**
     * Failures eligible for promotion to base failures.
     *
     * @param  Builder<RlmFailure>  $query
     * @return Builder<RlmFailure>
     */
    public function scopePromotable(Builder $query): Builder
    {
        return $query->where('report_count', '>=', 3)
            ->where('project_count', '>=', 2)
            ->where('promoted_to_base', false);
    }

    /**
     * Filter by entity context using JSONB queries.
     *
     * @param  Builder<RlmFailure>  $query
     * @param  array<string, mixed>  $context
     * @return Builder<RlmFailure>
     */
    public function scopeByEntityContext(Builder $query, array $context): Builder
    {
        foreach ($context as $key => $value) {
            $query->whereJsonContains('entity_context->'.$key, $value);
        }

        return $query;
    }

    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->title,
            $this->description,
            $this->root_cause,
            $this->preventive_rule,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'failure_code' => $this->failure_code,
            'category' => $this->category?->value,
            'severity' => $this->severity?->value,
            'status' => $this->status ? (string) $this->status : null,
            'title' => $this->title,
            'description' => $this->description,
            'root_cause' => $this->root_cause,
            'fix' => $this->fix,
            'preventive_rule' => $this->preventive_rule,
            'scaffolding_fixed' => $this->scaffolding_fixed,
            'promoted_to_base' => $this->promoted_to_base,
            'report_count' => $this->report_count,
            'project_count' => $this->project_count,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_rlm_failures';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['title', 'description', 'failure_code', 'category'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['title', 'description', 'failure_code', 'root_cause', 'fix'];
    }

    public function shouldBeSearchable(): bool
    {
        if (! config('aicl.features.rlm_search', true)) {
            return false;
        }

        if (method_exists($this, 'trashed') && $this->trashed()) {
            return false;
        }

        return true;
    }

    protected static function newFactory(): RlmFailureFactory
    {
        return RlmFailureFactory::new();
    }
}
