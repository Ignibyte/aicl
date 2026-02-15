<?php

namespace Aicl\Models;

use Aicl\Database\Factories\GoldenAnnotationFactory;
use Aicl\Enums\AnnotationCategory;
use Aicl\Traits\HasEmbeddings;
use Aicl\Traits\HasSearchableFields;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $annotation_key
 * @property string $file_path
 * @property int|null $line_number
 * @property string $annotation_text
 * @property string|null $rationale
 * @property array $feature_tags
 * @property string|null $pattern_name
 * @property AnnotationCategory $category
 * @property bool $is_active
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, KnowledgeLink> $sourceLinks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, KnowledgeLink> $targetLinks
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forFile(string $filePath)
 * @method static Builder<static> inCategory(AnnotationCategory|string $category)
 * @method static Builder<static> withFeatureTag(string $tag)
 * @method static Builder<static> withAnyFeatureTag(array $tags)
 * @method static Builder<static> forPattern(string $patternName)
 */
class GoldenAnnotation extends Model
{
    use HasEmbeddings;

    /** @use HasFactory<GoldenAnnotationFactory> */
    use HasFactory;

    use HasSearchableFields;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'annotation_key',
        'file_path',
        'line_number',
        'annotation_text',
        'rationale',
        'feature_tags',
        'pattern_name',
        'category',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'feature_tags' => 'array',
            'category' => AnnotationCategory::class,
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
     * @return MorphMany<KnowledgeLink, $this>
     */
    public function sourceLinks(): MorphMany
    {
        return $this->morphMany(KnowledgeLink::class, 'source');
    }

    /**
     * @return MorphMany<KnowledgeLink, $this>
     */
    public function targetLinks(): MorphMany
    {
        return $this->morphMany(KnowledgeLink::class, 'target');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForFile(Builder $query, string $filePath): Builder
    {
        return $query->where('file_path', $filePath);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInCategory(Builder $query, AnnotationCategory|string $category): Builder
    {
        $value = $category instanceof AnnotationCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithFeatureTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('feature_tags', $tag);
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<int, string>  $tags
     * @return Builder<static>
     */
    public function scopeWithAnyFeatureTag(Builder $query, array $tags): Builder
    {
        return $query->where(function (Builder $q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('feature_tags', $tag);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPattern(Builder $query, string $patternName): Builder
    {
        return $query->where('pattern_name', $patternName);
    }

    public function embeddingText(): string
    {
        return implode(' ', array_filter([
            $this->annotation_text,
            $this->rationale,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'annotation_key' => $this->annotation_key,
            'annotation_text' => $this->annotation_text,
            'rationale' => $this->rationale,
            'category' => $this->category->value,
            'pattern_name' => $this->pattern_name,
            'feature_tags' => $this->feature_tags,
            'is_active' => $this->is_active,
        ];

        if ($embedding = $this->getCachedEmbedding()) {
            $array['embedding'] = $embedding;
        }

        return $array;
    }

    public function searchableAs(): string
    {
        return 'aicl_golden_annotations';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['annotation_key', 'annotation_text', 'pattern_name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['annotation_text', 'rationale', 'annotation_key', 'pattern_name', 'category'];
    }

    public function shouldBeSearchable(): bool
    {
        if (! config('aicl.features.rlm_search', true)) {
            return false;
        }

        if ($this->trashed()) {
            return false;
        }

        return $this->is_active;
    }

    protected static function newFactory(): GoldenAnnotationFactory
    {
        return GoldenAnnotationFactory::new();
    }
}
