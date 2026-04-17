<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\AiConversationFactory;
use Aicl\States\AiConversation\Active;
use Aicl\States\AiConversation\AiConversationState;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * AI Conversation model.
 *
 * Represents a multi-turn conversation between a user and an AI agent.
 * Tracks message count, token usage, and supports compaction (summarization).
 *
 * @property string              $id
 * @property string|null         $title
 * @property int                 $user_id
 * @property string              $ai_agent_id
 * @property int                 $message_count
 * @property int                 $token_count
 * @property string|null         $summary
 * @property bool                $is_pinned
 * @property string|null         $context_page
 * @property Carbon|null         $last_message_at
 * @property AiConversationState $state
 * @property Carbon              $created_at
 * @property Carbon              $updated_at
 * @property Carbon|null         $deleted_at
 */
class AiConversation extends Model
{
    use HasAuditTrail;
    use HasEntityEvents;

    /** @use HasFactory<AiConversationFactory> */
    use HasFactory;

    use HasStandardScopes;
    use HasStates;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'title',
        'user_id',
        'ai_agent_id',
        'message_count',
        'token_count',
        'summary',
        'is_pinned',
        'context_page',
        'last_message_at',
        'state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => AiConversationState::class,
            'message_count' => 'integer',
            'token_count' => 'integer',
            'is_pinned' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiAgent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    /**
     * @return HasMany<AiMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Conversations belonging to a specific user.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Active conversations only.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereState('state', Active::class);
    }

    /**
     * Ordered by most recent message.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('last_message_at');
    }

    /**
     * Pinned conversations first, then by updated_at.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePinned(Builder $query): Builder
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $query->where('is_pinned', true)->orderByDesc('updated_at');
        // @codeCoverageIgnoreEnd
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?? 'New Conversation';
    }

    public function getIsCompactableAttribute(): bool
    {
        $threshold = (int) config('aicl.ai.assistant.compaction_threshold', 50);

        return $this->message_count > $threshold && $this->summary === null;
    }

    // ──────────────────────────────────────────────
    // Searchable columns override (BF-001/BF-005)
    // ──────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['title'];
    }

    // ──────────────────────────────────────────────
    // Factory
    // ──────────────────────────────────────────────

    protected static function newFactory(): AiConversationFactory
    {
        return AiConversationFactory::new();
    }
}
