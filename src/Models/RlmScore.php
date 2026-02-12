<?php

namespace Aicl\Models;

use Aicl\Database\Factories\RlmScoreFactory;
use Aicl\Enums\ScoreType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $entity_name
 * @property ScoreType $score_type
 * @property int $passed
 * @property int $total
 * @property float $percentage
 * @property int $errors
 * @property int $warnings
 * @property array|null $details
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User|null $owner
 *
 * @method static Builder<static> forEntity(string $entityName)
 * @method static Builder<static> ofType(ScoreType|string $type)
 * @method static Builder<static> perfect()
 */
class RlmScore extends Model
{
    /** @use HasFactory<RlmScoreFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_name',
        'score_type',
        'passed',
        'total',
        'percentage',
        'errors',
        'warnings',
        'details',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'score_type' => ScoreType::class,
            'passed' => 'integer',
            'total' => 'integer',
            'percentage' => 'decimal:2',
            'errors' => 'integer',
            'warnings' => 'integer',
            'details' => 'array',
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
    public function scopeForEntity(Builder $query, string $entityName): Builder
    {
        return $query->where('entity_name', $entityName);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfType(Builder $query, ScoreType|string $type): Builder
    {
        $value = $type instanceof ScoreType ? $type->value : $type;

        return $query->where('score_type', $value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePerfect(Builder $query): Builder
    {
        return $query->whereColumn('passed', 'total');
    }

    protected static function newFactory(): RlmScoreFactory
    {
        return RlmScoreFactory::new();
    }
}
