<?php

// PATTERN: Golden example model — demonstrates all AICL entity patterns.
// PATTERN: Namespace is Aicl\Models for package models, App\Models for app-level models.
// PATTERN: Generated entities from aicl:make-entity go into Aicl\Models (package path).

namespace Aicl\Models;

// PATTERN: Import all contracts that match the traits used.
use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Contracts\Stateful;
use Aicl\Contracts\Taggable;
use Aicl\Database\Factories\ProjectFactory;
use Aicl\Enums\ProjectPriority;
use Aicl\States\ProjectState;
// PATTERN: Import traits from Aicl\Traits namespace.
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasSearchableFields;
use Aicl\Traits\HasStandardScopes;
use Aicl\Traits\HasTagging;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
// PATTERN: HasStates from Spatie for state machine support.
use Spatie\ModelStates\HasStates;

/**
 * PATTERN: PHPDoc block documents all properties for IDE autocomplete.
 * Include @property for every column and @property-read for relationships.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ProjectState $status
 * @property ProjectPriority $priority
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property float|null $budget
 * @property bool $is_active
 * @property int $owner_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 */
// PATTERN: Implement contracts that match the traits used.
class Project extends Model implements Auditable, HasEntityLifecycle, Stateful, Taggable
{
    // PATTERN: @use generic annotation for HasFactory.
    /** @use HasFactory<ProjectFactory> */
    use HasAuditTrail;

    use HasEntityEvents;
    use HasFactory;
    use HasSearchableFields;
    use HasStandardScopes;
    use HasStates;
    use HasTagging;

    // PATTERN: Always include SoftDeletes for entity models.
    use SoftDeletes;

    /**
     * PATTERN: Explicit $fillable array — never use $guarded = [].
     * List every field that can be mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'budget',
        'is_active',
        'owner_id',
    ];

    // PATTERN: Use casts() method (Laravel 11+), NOT the $casts property.
    protected function casts(): array
    {
        return [
            // PATTERN: Cast state columns to their State class.
            'status' => ProjectState::class,
            // PATTERN: Cast enum columns to their Enum class.
            'priority' => ProjectPriority::class,
            'start_date' => 'date',
            'end_date' => 'date',
            // PATTERN: Use 'decimal:2' for money fields.
            'budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // PATTERN: Every entity has an owner (BelongsTo User).
    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // PATTERN: Many-to-many with pivot data uses withPivot() and withTimestamps().
    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    // PATTERN: Override searchableFields() to define which columns are full-text indexed.
    /**
     * @return array<int, string>
     */
    protected function searchableFields(): array
    {
        return ['name', 'description', 'status', 'priority'];
    }

    // PATTERN: Package models MUST override newFactory() to resolve the correct factory class.
    // Without this, Laravel looks in Database\Factories\ instead of Aicl\Database\Factories\.
    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
