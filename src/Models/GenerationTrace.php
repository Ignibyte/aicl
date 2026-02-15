<?php

namespace Aicl\Models;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Database\Factories\GenerationTraceFactory;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id UUID primary key
 * @property string $entity_name Entity that was generated
 * @property string|null $project_hash SHA256 hash of originating project
 * @property string $scaffolder_args Full scaffolder command arguments
 * @property array|null $file_manifest List of files created
 * @property float|null $structural_score RLM structural validation score
 * @property float|null $semantic_score Semantic validation score
 * @property string|null $test_results Test run output
 * @property array|null $fixes_applied List of fixes applied during pipeline
 * @property int $fix_iterations Number of fix cycles needed
 * @property int|null $pipeline_duration Duration in seconds
 * @property array|null $agent_versions Agent version info
 * @property bool $is_processed Whether trace has been analyzed by pattern discovery
 * @property string|null $aicl_version AICL package version
 * @property string|null $laravel_version Laravel framework version
 * @property int $known_failure_count Count of failures covered by existing lessons
 * @property int $novel_failure_count Count of failures not covered by any lesson
 * @property array|null $surfaced_lesson_codes DL codes surfaced during generation
 * @property array|null $failure_codes_hit Failure codes that actually occurred
 * @property bool $is_active
 * @property int $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $owner
 */
class GenerationTrace extends Model implements Auditable, HasEntityLifecycle
{
    use HasAuditTrail;
    use HasEntityEvents;

    /** @use HasFactory<GenerationTraceFactory> */
    use HasFactory;

    use HasStandardScopes;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_name',
        'project_hash',
        'scaffolder_args',
        'file_manifest',
        'structural_score',
        'semantic_score',
        'test_results',
        'fixes_applied',
        'fix_iterations',
        'pipeline_duration',
        'agent_versions',
        'is_processed',
        'aicl_version',
        'laravel_version',
        'known_failure_count',
        'novel_failure_count',
        'surfaced_lesson_codes',
        'failure_codes_hit',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'file_manifest' => 'array',
            'structural_score' => 'decimal:2',
            'semantic_score' => 'decimal:2',
            'fixes_applied' => 'array',
            'fix_iterations' => 'integer',
            'pipeline_duration' => 'integer',
            'agent_versions' => 'array',
            'is_processed' => 'boolean',
            'known_failure_count' => 'integer',
            'novel_failure_count' => 'integer',
            'surfaced_lesson_codes' => 'array',
            'failure_codes_hit' => 'array',
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
     * Scope to unprocessed traces (not yet analyzed by pattern discovery).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Scope to traces for a specific entity name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByEntity($query, string $name)
    {
        return $query->where('entity_name', $name);
    }

    /**
     * Scope to traces for a specific project hash.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByProject($query, string $hash)
    {
        return $query->where('project_hash', $hash);
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['entity_name', 'scaffolder_args'];
    }

    protected static function newFactory(): GenerationTraceFactory
    {
        return GenerationTraceFactory::new();
    }
}
