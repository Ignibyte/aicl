<?php

namespace Aicl\Traits;

use Aicl\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adds social account relationship to User model.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSocialAccounts
{
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    public function getSocialAccount(string $provider): ?SocialAccount
    {
        /** @var SocialAccount|null */
        return $this->socialAccounts()->where('provider', $provider)->first();
    }

    public function linkSocialAccount(string $provider, string $providerId, ?string $token = null, ?string $refreshToken = null, ?\DateTimeInterface $expiresAt = null, ?string $avatarUrl = null): SocialAccount
    {
        /** @var SocialAccount */
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
    }

    public function getSocialAvatarUrl(): ?string
    {
        /** @var string|null */
        return $this->socialAccounts()
            ->whereNotNull('avatar_url')
            ->orderByDesc('updated_at')
            ->value('avatar_url');
    }

    public function unlinkSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->delete() > 0;
    }
}
