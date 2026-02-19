<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\RlmLessonFactory;
use Aicl\Enums\LessonType;
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
 * @property string $topic
 * @property string|null $subtopic
 * @property string $summary
 * @property string $detail
 * @property string|null $tags
 * @property array|null $context_tags
 * @property string|null $source
 * @property LessonType $lesson_type
 * @property string|null $promotion_reason
 * @property float $confidence
 * @property bool $is_verified
 * @property int $view_count
 * @property bool $is_active
 * @property bool $needs_review
 * @property int $owner_id
 *
 * @method static Builder<static> verified()
 * @method static Builder<static> unverified()
 * @method static Builder<static> byTopic(string $topic)
 * @method static Builder<static> byContextTag(string $tag)
 * @method static Builder<static> byType(LessonType|string $type)
 * @method static Builder<static> surfaceable()
 * @method static Builder<static> needsReview()
 */
class RlmLesson extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEmbeddings;
    use HasEntityEvents;

    /** @use HasFactory<RlmLessonFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'topic',
        'subtopic',
        'summary',
        'detail',
        'tags',
        'context_tags',
        'source',
        'lesson_type',
        'promotion_reason',
        'confidence',
        'is_verified',
        'view_count',
        'is_active',
        'needs_review',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'context_tags' => 'array',
            'lesson_type' => LessonType::class,
            'confidence' => 'decimal:2',
            'is_verified' => 'boolean',
            'view_count' => 'integer',
            'is_active' => 'boolean',
            'needs_review' => 'boolean',
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
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('is_verified', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByTopic(Builder $query, string $topic): Builder
    {
        return $query->where('topic', $topic);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByContextTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('context_tags', $tag);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByType(Builder $query, LessonType|string $type): Builder
    {
        $value = $type instanceof LessonType ? $type->value : $type;

        return $query->where('lesson_type', $value);
    }

    /**
     * Filter to only lessons that should be surfaced in recall (instruction + prevention_rule).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSurfaceable(Builder $query): Builder
    {
        return $query->whereIn('lesson_type', [
            LessonType::Instruction->value,
            LessonType::PreventionRule->value,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('needs_review', true);
    }

    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->summary,
            $this->detail,
            $this->tags,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'topic' => $this->topic,
            'subtopic' => $this->subtopic,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'tags' => $this->tags,
            'confidence' => (float) $this->confidence,
            'is_verified' => $this->is_verified,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_rlm_lessons';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['topic', 'summary', 'detail', 'tags'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['topic', 'summary', 'detail', 'tags'];
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

    protected static function newFactory(): RlmLessonFactory
    {
        return RlmLessonFactory::new();
    }
}
