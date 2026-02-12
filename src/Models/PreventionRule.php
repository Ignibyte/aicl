<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\PreventionRuleFactory;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEmbeddings;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasSearchableFields;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id UUID primary key
 * @property string|null $rlm_failure_id Related failure (nullable)
 * @property array|null $trigger_context JSON context that triggers this rule
 * @property string $rule_text The prevention advice text
 * @property float $confidence Confidence score 0.0-1.0
 * @property int $priority Priority ordering (higher = more important)
 * @property bool $is_active
 * @property int $applied_count Times this rule has been applied
 * @property \Illuminate\Support\Carbon|null $last_applied_at
 * @property int $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read RlmFailure|null $failure
 */
class PreventionRule extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEmbeddings;
    use HasEntityEvents;

    /** @use HasFactory<PreventionRuleFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rlm_failure_id',
        'trigger_context',
        'rule_text',
        'confidence',
        'priority',
        'is_active',
        'applied_count',
        'last_applied_at',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_context' => 'array',
            'confidence' => 'decimal:2',
            'priority' => 'integer',
            'applied_count' => 'integer',
            'is_active' => 'boolean',
            'last_applied_at' => 'datetime',
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
     * Alias for failure() relationship.
     *
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
     * Scope to rules matching a given entity context.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  array<string, mixed>  $context
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForContext($query, array $context)
    {
        foreach ($context as $key => $value) {
            $query->whereJsonContains("trigger_context->{$key}", $value);
        }

        return $query;
    }

    /**
     * Scope to high-confidence rules.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeHighConfidence($query, float $threshold = 0.7)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    public function embeddingText(): string
    {
        $parts = [$this->rule_text];

        if (! empty($this->trigger_context)) {
            $parts[] = json_encode($this->trigger_context);
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'rule_text' => $this->rule_text,
            'trigger_context' => ! empty($this->trigger_context) ? json_encode($this->trigger_context) : null,
            'confidence' => (float) $this->confidence,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_prevention_rules';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['rule_text'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['rule_text'];
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

    protected static function newFactory(): PreventionRuleFactory
    {
        return PreventionRuleFactory::new();
    }
}
