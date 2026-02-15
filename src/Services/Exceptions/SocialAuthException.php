<?php

namespace Aicl\Services\Exceptions;

/**
 * Non-recoverable social/SAML authentication errors.
 *
 * Thrown by SocialAuthService when the authentication flow
 * cannot proceed (missing email, auto-create disabled).
 */
class SocialAuthException extends \RuntimeException
{
    public static function missingEmail(): self
    {
        return new self('No email address was provided by the identity provider.');
    }

    public static function autoCreateDisabled(string $email): self
    {
        return new self('No account exists for this email. Contact your administrator.');
    }
}
