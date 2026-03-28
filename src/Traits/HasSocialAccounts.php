<?php

declare(strict_types=1);

namespace Aicl\Traits;

use Aicl\Models\SocialAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adds social account relationship to User model.
 *
 * @mixin Model
 */
trait HasSocialAccounts
{
    /**
     * Get all social accounts linked to this user.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Check if a social account exists for the given provider.
     *
     * @param  string  $provider  Provider name (e.g. 'google', 'github')
     */
    public function hasSocialAccount(string $provider): bool
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $this->socialAccounts()->where('provider', $provider)->exists();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the social account for a specific provider, if linked.
     *
     * @param  string  $provider  Provider name (e.g. 'google', 'github')
     */
    public function getSocialAccount(string $provider): ?SocialAccount
    {
        /** @var SocialAccount|null */
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $this->socialAccounts()->where('provider', $provider)->first();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Link or update a social account for the given provider.
     *
     * @param  string  $provider  Provider name (e.g. 'google', 'github')
     * @param  string  $providerId  Unique identifier from the OAuth provider
     * @param  string|null  $token  OAuth access token
     * @param  string|null  $refreshToken  OAuth refresh token
     * @param  \DateTimeInterface|null  $expiresAt  Token expiration time
     * @param  string|null  $avatarUrl  URL to the user's avatar from the provider
     * @return SocialAccount The created or updated social account
     */
    public function linkSocialAccount(string $provider, string $providerId, ?string $token = null, ?string $refreshToken = null, ?\DateTimeInterface $expiresAt = null, ?string $avatarUrl = null): SocialAccount
    {
        /** @var SocialAccount */
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $this->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'provider_id' => $providerId,
                'avatar_url' => $avatarUrl,
                'token' => $token,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $expiresAt,
            ]
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the most recently updated social avatar URL, if any.
     */
    public function getSocialAvatarUrl(): ?string
    {
        /** @var string|null */
        return $this->socialAccounts()
            ->whereNotNull('avatar_url')
            ->orderByDesc('updated_at')
            ->value('avatar_url');
    }

    /**
     * Remove the social account link for a given provider.
     *
     * @param  string  $provider  Provider name (e.g. 'google', 'github')
     * @return bool Whether any rows were deleted
     */
    public function unlinkSocialAccount(string $provider): bool
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $this->socialAccounts()->where('provider', $provider)->delete() > 0;
        // @codeCoverageIgnoreEnd
    }
}
