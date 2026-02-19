<?php

namespace Aicl\Models;

use Aicl\Database\Factories\DistilledLessonFactory;
use Aicl\Traits\HasEmbeddings;
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
 * @property string $lesson_code
 * @property string $title
 * @property string $guidance
 * @property string $target_agent
 * @property int $target_phase
 * @property array|null $trigger_context
 * @property array $source_failure_codes
 * @property array|null $source_lesson_ids
 * @property float $impact_score
 * @property float|null $base_impact_score
 * @property float $confidence
 * @property int $applied_count
 * @property int $prevented_count
 * @property int $ignored_count
 * @property int $surfaced_count
 * @property bool $is_active
 * @property \Carbon\Carbon $last_distilled_at
 * @property int $generation
 * @property int $owner_id
 *
 * @method static Builder<static> forAgent(string $agent)
 * @method static Builder<static> forPhase(int $phase)
 * @method static Builder<static> highImpact(float $minScore)
 */
class DistilledLesson extends Model
{
    use HasEmbeddings;

    /** @use HasFactory<DistilledLessonFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_code',
        'title',
        'guidance',
        'target_agent',
        'target_phase',
        'trigger_context',
        'source_failure_codes',
        'source_lesson_ids',
        'impact_score',
        'base_impact_score',
        'confidence',
        'applied_count',
        'prevented_count',
        'ignored_count',
        'surfaced_count',
        'is_active',
        'last_distilled_at',
        'generation',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_context' => 'array',
            'source_failure_codes' => 'array',
            'source_lesson_ids' => 'array',
            'impact_score' => 'decimal:2',
            'base_impact_score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'applied_count' => 'integer',
            'prevented_count' => 'integer',
            'ignored_count' => 'integer',
            'surfaced_count' => 'integer',
            'is_active' => 'boolean',
            'last_distilled_at' => 'datetime',
            'generation' => 'integer',
            'target_phase' => 'integer',
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
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAgent(Builder $query, string $agent): Builder
    {
        return $query->where('target_agent', $agent);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPhase(Builder $query, int $phase): Builder
    {
        return $query->where('target_phase', $phase);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHighImpact(Builder $query, float $minScore = 5.0): Builder
    {
        return $query->where('impact_score', '>=', $minScore);
    }

    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->title,
            $this->guidance,
            $this->target_agent,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'lesson_code' => $this->lesson_code,
            'title' => $this->title,
            'guidance' => $this->guidance,
            'target_agent' => $this->target_agent,
            'target_phase' => $this->target_phase,
            'impact_score' => (float) $this->impact_score,
            'confidence' => (float) $this->confidence,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_distilled_lessons';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['title', 'guidance', 'lesson_code', 'target_agent'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['title', 'guidance', 'lesson_code', 'target_agent'];
    }

    public function shouldBeSearchable(): bool
    {
        if (! config('aicl.features.rlm_search', true)) {
            return false;
        }

        if ($this->trashed()) {
            return false;
        }

        return true;
    }

    protected static function newFactory(): DistilledLessonFactory
    {
        return DistilledLessonFactory::new();
    }
}
