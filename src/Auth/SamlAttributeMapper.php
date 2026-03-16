<?php

namespace Aicl\Auth;

use Aicl\Http\Controllers\SocialAuthController;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use LightSaml\Model\Assertion\Attribute;

/**
 * Maps SAML assertion attributes to Laravel user fields and roles.
 *
 * Handles the translation between SAML IdP attribute names (which vary by provider
 * and schema -- OID URNs, WS-Federation URIs, or plain names) and the standard user
 * model fields. Supports configurable attribute maps and role mappings via aicl.saml config.
 *
 * Can be replaced with a custom mapper class via aicl.saml.mapper_class config.
 *
 * @see SocialAuthController  Uses this for SAML SSO login
 */
class SamlAttributeMapper
{
    /**
     * Default SAML attribute name aliases for common user fields.
     * Each user field maps to an array of possible SAML attribute names.
     * The mapper tries each in order and uses the first non-null match.
     *
     * @var array<string, list<string>>
     */
    protected array $defaultAttributeMap = [
        'email' => [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'urn:oid:0.9.2342.19200300.100.1.3',
            'email',
            'Email',
        ],
        'name' => [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
            'urn:oid:2.5.4.3',
            'displayName',
            'name',
        ],
        'first_name' => [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
            'urn:oid:2.5.4.42',
            'first_name',
            'firstName',
        ],
        'last_name' => [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
            'urn:oid:2.5.4.4',
            'last_name',
            'lastName',
        ],
    ];

    /**
     * Resolve user attributes from the SAML assertion.
     *
     * @return array<string, mixed>
     */
    public function resolveAttributes(SocialiteUser $socialiteUser): array
    {
        $attributes = [];
        $rawAttributes = $this->getRawAttributes($socialiteUser);

        // Merge config overrides with defaults (config takes priority)
        $configMap = config('aicl.saml.attribute_map', []);
        $fullMap = array_merge($this->defaultAttributeMap, $configMap);

        foreach ($fullMap as $field => $aliases) {
            $value = $this->findAttributeValue($rawAttributes, $aliases);
            if ($value !== null) {
                $attributes[$field] = $value;
            }
        }

        // Fall back to Socialite's built-in mappings for standard fields
        if (empty($attributes['email'])) {
            $attributes['email'] = $socialiteUser->getEmail();
        }

        if (empty($attributes['name'])) {
            $attributes['name'] = $socialiteUser->getName();
        }

        return $attributes;
    }

    /**
     * Resolve Laravel role names from the SAML assertion.
     *
     * @return list<string>
     */
    public function resolveRoles(SocialiteUser $socialiteUser): array
    {
        $roleMapConfig = config('aicl.saml.role_map', []);
        $sourceAttribute = $roleMapConfig['source_attribute'] ?? 'groups';
        $map = $roleMapConfig['map'] ?? [];

        if (empty($map)) {
            return [config('aicl.saml.default_role', 'viewer')];
        }

        $rawAttributes = $this->getRawAttributes($socialiteUser);
        $groupValues = $this->findAttributeValue($rawAttributes, [$sourceAttribute]);

        if ($groupValues === null) {
            return [config('aicl.saml.default_role', 'viewer')];
        }

        // Normalize to array (IdP may send single string or array)
        $groupValues = is_array($groupValues) ? $groupValues : [$groupValues];

        $resolvedRoles = [];
        foreach ($groupValues as $groupValue) {
            if (isset($map[$groupValue])) {
                $mappedRoles = is_array($map[$groupValue]) ? $map[$groupValue] : [$map[$groupValue]];
                $resolvedRoles = array_merge($resolvedRoles, $mappedRoles);
            }
        }

        $resolvedRoles = array_unique($resolvedRoles);

        if (empty($resolvedRoles)) {
            return [config('aicl.saml.default_role', 'viewer')];
        }

        return array_values($resolvedRoles);
    }

    /**
     * Look up a SAML attribute by trying multiple alias names.
     */
    public function getAttribute(SocialiteUser $socialiteUser, string $field): mixed
    {
        $configMap = config('aicl.saml.attribute_map', []);
        $fullMap = array_merge($this->defaultAttributeMap, $configMap);

        $aliases = $fullMap[$field] ?? [$field];
        $rawAttributes = $this->getRawAttributes($socialiteUser);

        return $this->findAttributeValue($rawAttributes, $aliases);
    }

    /**
     * Get all raw SAML attributes from the Socialite user.
     *
     * @return array<string, mixed>
     */
    public function getRawAttributes(SocialiteUser $socialiteUser): array
    {
        $raw = $socialiteUser->getRaw();

        if (! is_array($raw)) {
            return [];
        }

        // The socialiteproviders/saml2 package stores raw attributes as
        // LightSaml\Model\Assertion\Attribute objects. Normalize them to
        // a simple key => value array.
        $normalized = [];
        foreach ($raw as $attribute) {
            if ($attribute instanceof Attribute) {
                $values = $attribute->getAllAttributeValues();
                $normalized[$attribute->getName()] = count($values) === 1 ? $values[0] : $values;
            }
        }

        return $normalized;
    }

    /**
     * Search raw attributes for the first matching alias with a non-null value.
     *
     * @param  array<string, mixed>  $rawAttributes
     * @param  list<string>  $aliases
     */
    protected function findAttributeValue(array $rawAttributes, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            if (isset($rawAttributes[$alias]) && $rawAttributes[$alias] !== null) {
                return $rawAttributes[$alias];
            }
        }

        return null;
    }
}
