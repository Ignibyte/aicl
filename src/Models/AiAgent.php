<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\AI\AiProviderFactory;
use Aicl\Database\Factories\AiAgentFactory;
use Aicl\Enums\AiProvider;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\AiAgentState;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * AI Agent model.
 *
 * Persistent agent definitions for the AI Assistant system.
 * Each agent has a provider, model, system prompt, and configuration
 * that controls how it responds in the floating chat widget.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property AiProvider $provider
 * @property string $model
 * @property string|null $system_prompt
 * @property int $max_tokens
 * @property float $temperature
 * @property int $context_window
 * @property int $context_messages
 * @property bool $is_active
 * @property string|null $icon
 * @property string|null $color
 * @property int $sort_order
 * @property array<int, string>|null $suggested_prompts
 * @property array<string, mixed>|null $capabilities
 * @property array<int, string>|null $visible_to_roles
 * @property int|null $max_requests_per_minute
 * @property AiAgentState $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class AiAgent extends Model
{
    use HasAuditTrail;
    use HasEntityEvents;

    /** @use HasFactory<AiAgentFactory> */
    use HasFactory;

    use HasStandardScopes;
    use HasStates;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ai_agents';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'provider',
        'model',
        'system_prompt',
        'max_tokens',
        'temperature',
        'context_window',
        'context_messages',
        'is_active',
        'icon',
        'color',
        'sort_order',
        'suggested_prompts',
        'capabilities',
        'visible_to_roles',
        'max_requests_per_minute',
        'state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'state' => AiAgentState::class,
            'max_tokens' => 'integer',
            'temperature' => 'decimal:2',
            'context_window' => 'integer',
            'context_messages' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'suggested_prompts' => 'array',
            'capabilities' => 'array',
            'visible_to_roles' => 'array',
            'max_requests_per_minute' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * @return HasMany<AiConversation, $this>
     */
    public function conversations(): HasMany
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $this->hasMany(AiConversation::class);
        // @codeCoverageIgnoreEnd
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Agents that are both state=active AND is_active toggle on.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForWidget(Builder $query): Builder
    {
        return $query
            ->whereState('state', Active::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Filter agents visible to a specific set of roles.
     *
     * @param  Builder<self>  $query
     * @param  array<string>  $userRoles
     * @return Builder<self>
     */
    public function scopeVisibleToRoles(Builder $query, array $userRoles): Builder
    {
        return $query->where(function (Builder $q) use ($userRoles): void {
            $q->whereNull('visible_to_roles')
                ->orWhereJsonLength('visible_to_roles', 0);

            foreach ($userRoles as $role) {
                $q->orWhereJsonContains('visible_to_roles', $role);
            }
        });
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getIsConfiguredAttribute(): bool
    {
        return AiProviderFactory::isConfigured($this->provider->value);
    }

    // ──────────────────────────────────────────────
    // Searchable columns override (BF-001/BF-005)
    // ──────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'slug', 'description'];
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Check if a user (by their roles) can see this agent.
     *
     * @param  array<string>  $userRoles
     */
    public function isVisibleTo(array $userRoles): bool
    {
        if ($this->visible_to_roles === null || $this->visible_to_roles === []) {
            return true;
        }

        return count(array_intersect($this->visible_to_roles, $userRoles)) > 0;
    }

    /**
     * Check if a specific user has access to this agent.
     * Verifies role membership against visible_to_roles.
     */
    public function isAccessibleByUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->visible_to_roles === null || $this->visible_to_roles === []) {
            return true;
        }

        if (! method_exists($user, 'getRoleNames')) {
            return true;
        }

        return $this->isVisibleTo($user->getRoleNames()->toArray());
    }

    /**
     * Whether this agent has function/tool calling enabled.
     */
    public function hasToolsEnabled(): bool
    {
        $capabilities = $this->capabilities;

        if (! is_array($capabilities)) {
            return false;
        }

        return (bool) ($capabilities['tools_enabled'] ?? false);
    }

    /**
     * Get the list of allowed tool FQCNs for this agent.
     * Returns null if all tools are allowed (no restriction).
     * Returns empty array if tools are disabled.
     *
     * @return array<string>|null
     */
    public function getAllowedTools(): ?array
    {
        if (! $this->hasToolsEnabled()) {
            return [];
        }

        $capabilities = $this->capabilities;
        $allowedTools = $capabilities['allowed_tools'] ?? null;

        if (! is_array($allowedTools) || empty($allowedTools)) {
            return null; // null = all registered tools
        }

        return $allowedTools;
    }

    // ──────────────────────────────────────────────
    // Factory
    // ──────────────────────────────────────────────

    protected static function newFactory(): AiAgentFactory
    {
        return AiAgentFactory::new();
    }
}
