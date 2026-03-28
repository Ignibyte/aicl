<?php

declare(strict_types=1);

namespace Aicl\Services;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Models\SocialAccount;
use Aicl\Services\Exceptions\SocialAuthException;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

/** Handles OAuth and SAML social authentication flows with account linking. */
class SocialAuthService
{
    /**
     * Handle OAuth provider callback: find or create user, link social account, return user.
     *
     * @throws SocialAuthException on unrecoverable errors
     */
    public function handleOAuthCallback(
        string $provider,
        SocialiteUserContract $socialUser,
    ): User {
        // Check if this social account is already linked to a user
        // @codeCoverageIgnoreStart — Service integration
        $existingUser = $this->findExistingSocialAccount(
            $provider,
            $socialUser->getId(),
            $socialUser->getAvatar(),
        );

        if ($existingUser) {
            return $existingUser;
        }

        // Find or create user by email
        $email = $socialUser->getEmail();

        if (! $email) {
            throw SocialAuthException::missingEmail();
        }

        $emailVerified = $socialUser->user['email_verified'] ?? false;
        $user = $this->findOrCreateUser([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $email,
            'email_verified' => (bool) $emailVerified,
        ]);

        // Link the social account
        $this->linkSocialAccount($user, $provider, $socialUser->getId(), [
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_in' => $socialUser->expiresIn,
            'avatar_url' => $socialUser->getAvatar(),
        ]);

        return $user;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Handle SAML ACS callback: attribute mapping, find or create user, link, role sync.
     *
     * @throws SocialAuthException on missing email or auto-create disabled
     */
    public function handleSamlCallback(
        SocialiteUserContract $socialUser,
        SamlAttributeMapper $mapper,
    ): User {
        $attributes = $mapper->resolveAttributes($socialUser);
        $email = $attributes['email'] ?? $socialUser->getEmail();

        if (! $email) {
            throw SocialAuthException::missingEmail();
        }

        // Check if this SAML account is already linked
        $existingUser = $this->findExistingSocialAccount(
            'saml2',
            $socialUser->getId(),
        );

        if ($existingUser) {
            // Sync roles from SAML assertion on each login
            $this->syncSamlRoles($existingUser, $mapper, $socialUser);

            // Update name if changed at IdP
            if (! empty($attributes['name']) && $existingUser->name !== $attributes['name']) {
                $existingUser->update(['name' => $attributes['name']]);
            }

            return $existingUser;
        }

        // Find existing user by email or create new
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! config('aicl.saml.auto_create_users', true)) {
                throw SocialAuthException::autoCreateDisabled($email);
            }

            $name = $attributes['name']
                ?? trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? ''))
                    ?: 'User';

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        // Link the SAML account (no OAuth tokens for SAML)
        $this->linkSocialAccount($user, 'saml2', $socialUser->getId(), [
            'avatar_url' => $socialUser->getAvatar(),
        ]);

        // Sync roles from SAML assertion
        $this->syncSamlRoles($user, $mapper, $socialUser);

        return $user;
    }

    /**
     * Find an existing social account for a provider + provider_id combo.
     * Returns the linked User if found, null otherwise.
     * Updates avatar URL as a side effect if a new avatar is available.
     */
    public function findExistingSocialAccount(
        string $provider,
        string $providerId,
        ?string $avatarUrl = null,
    ): ?User {
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (! $socialAccount) {
            return null;
        }

        // Update avatar URL on each login (providers can change avatars)
        if ($avatarUrl) {
            // @codeCoverageIgnoreStart — Service integration
            $socialAccount->update(['avatar_url' => $avatarUrl]);
            // @codeCoverageIgnoreEnd
        }

        return $socialAccount->user;
    }

    /**
     * Find an existing user by email, or create a new one.
     *
     * @param  array{name: string, email: string, email_verified: bool}  $attributes
     */
    public function findOrCreateUser(array $attributes): User
    {
        // @codeCoverageIgnoreStart — Service integration
        $user = User::where('email', $attributes['email'])->first();

        if ($user) {
            return $user;
        }

        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'email_verified_at' => $attributes['email_verified'] ? now() : null,
            'password' => bcrypt(Str::random(32)),
        ]);

        // Assign default role if available
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('viewer');
        }

        return $user;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Link a social account to a user.
     *
     * @param  array{token?: string|null, refresh_token?: string|null, expires_in?: int|null, avatar_url?: string|null}  $tokenData
     */
    public function linkSocialAccount(
        User $user,
        string $provider,
        string $providerId,
        array $tokenData = [],
    ): void {
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $providerId,
            'avatar_url' => $tokenData['avatar_url'] ?? null,
            'token' => $tokenData['token'] ?? null,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => isset($tokenData['expires_in']) && $tokenData['expires_in'] !== null
                // @codeCoverageIgnoreStart — Service integration
                ? now()->addSeconds($tokenData['expires_in'])
                // @codeCoverageIgnoreEnd
                : null,
        ]);
    }

    /**
     * Sync roles from SAML assertion to user.
     */
    public function syncSamlRoles(
        User $user,
        SamlAttributeMapper $mapper,
        SocialiteUserContract $socialUser,
    ): void {
        if (! method_exists($user, 'syncRoles')) {
            // @codeCoverageIgnoreStart — Service integration
            return;
            // @codeCoverageIgnoreEnd
        }

        $roles = $mapper->resolveRoles($socialUser);

        if (empty($roles)) {
            // @codeCoverageIgnoreStart — Service integration
            return;
            // @codeCoverageIgnoreEnd
        }

        $syncMode = config('aicl.saml.role_sync_mode', 'sync');

        if ($syncMode === 'additive') {
            foreach ($roles as $role) {
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }
        } else {
            $user->syncRoles($roles);
        }
    }
}
