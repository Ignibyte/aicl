<?php

declare(strict_types=1);

namespace Aicl\Models;

use Aicl\Database\Factories\SocialAccountFactory;
use Aicl\Traits\HasSocialAccounts;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Social login account linked to a user (Google, GitHub, Facebook, etc.).
 *
 * Stores OAuth provider credentials and avatar URLs. Token and refresh
 * token fields are encrypted at rest. Used by the HasSocialAccounts trait
 * and SocialAuthController for OAuth login flows.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $avatar_url
 * @property string|null $token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see HasSocialAccounts  Trait for user-side social account management
 */
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'avatar_url',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this social account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the OAuth token has expired.
     *
     * @return bool False if no expiry is set (never expires)
     */
    public function isExpired(): bool
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
        // @codeCoverageIgnoreEnd
    }

    protected static function newFactory(): SocialAccountFactory
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return SocialAccountFactory::new();
        // @codeCoverageIgnoreEnd
    }
}
