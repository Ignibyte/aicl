<?php

namespace Aicl\Models;

use Aicl\Database\Factories\KnowledgeLinkFactory;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\KnowledgeLinkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $source_type
 * @property string $source_id
 * @property string $target_type
 * @property string $target_id
 * @property KnowledgeLinkRelationship $relationship
 * @property KnowledgeLinkType|null $link_type
 * @property string|null $reference
 * @property float|null $confidence
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $source
 * @property-read Model $target
 *
 * @method static Builder<static> ofRelationship(KnowledgeLinkRelationship|string $relationship)
 * @method static Builder<static> highConfidence(float $threshold = 0.7)
 * @method static Builder<static> forSource(Model $model)
 * @method static Builder<static> forTarget(Model $model)
 * @method static Builder<static> ofLinkType(KnowledgeLinkType|string $type)
 * @method static Builder<static> proofLinks()
 */
class KnowledgeLink extends Model
{
    /** @use HasFactory<KnowledgeLinkFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'relationship',
        'link_type',
        'reference',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'relationship' => KnowledgeLinkRelationship::class,
            'link_type' => KnowledgeLinkType::class,
            'confidence' => 'decimal:2',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfRelationship(Builder $query, KnowledgeLinkRelationship|string $relationship): Builder
    {
        $value = $relationship instanceof KnowledgeLinkRelationship ? $relationship->value : $relationship;

        return $query->where('relationship', $value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHighConfidence(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('confidence', '>=', $threshold);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForSource(Builder $query, Model $model): Builder
    {
        return $query->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTarget(Builder $query, Model $model): Builder
    {
        return $query->where('target_type', $model->getMorphClass())
            ->where('target_id', $model->getKey());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInvolving(Builder $query, Model $model): Builder
    {
        $morphClass = $model->getMorphClass();
        $key = $model->getKey();

        return $query->where(function (Builder $q) use ($morphClass, $key) {
            $q->where(function (Builder $sub) use ($morphClass, $key) {
                $sub->where('source_type', $morphClass)->where('source_id', $key);
            })->orWhere(function (Builder $sub) use ($morphClass, $key) {
                $sub->where('target_type', $morphClass)->where('target_id', $key);
            });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfLinkType(Builder $query, KnowledgeLinkType|string $type): Builder
    {
        $value = $type instanceof KnowledgeLinkType ? $type->value : $type;

        return $query->where('link_type', $value);
    }

    /**
     * Filter to only links that have a proof type set.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProofLinks(Builder $query): Builder
    {
        return $query->whereNotNull('link_type');
    }

    protected static function newFactory(): KnowledgeLinkFactory
    {
        return KnowledgeLinkFactory::new();
    }
}
