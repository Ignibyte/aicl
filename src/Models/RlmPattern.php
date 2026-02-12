<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\RlmPatternFactory;
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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $target
 * @property string $check_regex
 * @property string $severity
 * @property float $weight
 * @property string $category
 * @property array|null $applies_when
 * @property string $source
 * @property bool $is_active
 * @property int $pass_count
 * @property int $fail_count
 * @property \Carbon\Carbon|null $last_evaluated_at
 * @property int $owner_id
 * @property-read User $owner
 * @property-read float $pass_rate
 */
class RlmPattern extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEmbeddings;
    use HasEntityEvents;

    /** @use HasFactory<RlmPatternFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'target',
        'check_regex',
        'severity',
        'weight',
        'category',
        'applies_when',
        'source',
        'is_active',
        'pass_count',
        'fail_count',
        'last_evaluated_at',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'applies_when' => 'array',
            'is_active' => 'boolean',
            'pass_count' => 'integer',
            'fail_count' => 'integer',
            'last_evaluated_at' => 'datetime',
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
     * Computed pass rate: pass_count / (pass_count + fail_count).
     */
    public function getPassRateAttribute(): float
    {
        $total = $this->pass_count + $this->fail_count;

        return $total > 0 ? round($this->pass_count / $total, 3) : 0.0;
    }

    /**
     * @param  Builder<RlmPattern>  $query
     * @return Builder<RlmPattern>
     */
    public function scopeForTarget(Builder $query, string $target): Builder
    {
        return $query->where('target', $target);
    }

    /**
     * @param  Builder<RlmPattern>  $query
     * @return Builder<RlmPattern>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<RlmPattern>  $query
     * @return Builder<RlmPattern>
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->name,
            $this->description,
            $this->target,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'target' => $this->target,
            'category' => $this->category,
            'severity' => $this->severity,
            'weight' => (float) $this->weight,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_rlm_patterns';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'description', 'target', 'category'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['name', 'description', 'target', 'category'];
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

    protected static function newFactory(): RlmPatternFactory
    {
        return RlmPatternFactory::new();
    }
}
