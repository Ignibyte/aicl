<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\AiMessageFactory;
use Aicl\Enums\AiMessageRole;
use Aicl\Traits\HasEntityEvents;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * AI Message model.
 *
 * Individual message within an AI conversation.
 * Stores the role (user/assistant/system), content, and token usage.
 *
 * @property string                    $id
 * @property string                    $ai_conversation_id
 * @property AiMessageRole             $role
 * @property string                    $content
 * @property int|null                  $token_count
 * @property array<string, mixed>|null $metadata
 * @property Carbon                    $created_at
 * @property Carbon                    $updated_at
 */
class AiMessage extends Model
{
    use HasEntityEvents;

    /** @use HasFactory<AiMessageFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'ai_messages';

    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'token_count',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'token_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<AiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
        // @codeCoverageIgnoreEnd
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isFromUser(): bool
    {
        return $this->role === AiMessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === AiMessageRole::Assistant;
    }

    public function isSystem(): bool
    {
        return $this->role === AiMessageRole::System;
    }

    // ──────────────────────────────────────────────
    // Factory
    // ──────────────────────────────────────────────

    protected static function newFactory(): AiMessageFactory
    {
        return AiMessageFactory::new();
    }
}
