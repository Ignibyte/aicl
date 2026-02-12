<?php

namespace Aicl\Tests\Unit\Auth;

use Aicl\Auth\SamlAttributeMapper;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use LightSaml\Model\Assertion\Attribute;
use Mockery;
use Tests\TestCase;

class SamlAttributeMapperTest extends TestCase
{
    protected SamlAttributeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new SamlAttributeMapper;

        // Reset SAML config to defaults for each test
        config([
            'aicl.saml.attribute_map' => [],
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [],
            ],
            'aicl.saml.default_role' => 'viewer',
        ]);
    }

    protected function mockSamlUser(array $rawAttributes = [], ?string $email = null, ?string $name = null): SocialiteUser
    {
        $lightSamlAttributes = [];
        foreach ($rawAttributes as $key => $value) {
            $attr = Mockery::mock(Attribute::class);
            $attr->shouldReceive('getName')->andReturn($key);
            $values = is_array($value) ? $value : [$value];
            $attr->shouldReceive('getAllAttributeValues')->andReturn($values);
            $lightSamlAttributes[] = $attr;
        }

        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getRaw')->andReturn($lightSamlAttributes);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);

        return $user;
    }

    public function test_resolve_attributes_from_standard_saml_uris(): void
    {
        $user = $this->mockSamlUser([
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'saml@example.com',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => 'SAML User',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => 'SAML',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname' => 'User',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('saml@example.com', $attributes['email']);
        $this->assertEquals('SAML User', $attributes['name']);
        $this->assertEquals('SAML', $attributes['first_name']);
        $this->assertEquals('User', $attributes['last_name']);
    }

    public function test_resolve_attributes_from_oid_uris(): void
    {
        $user = $this->mockSamlUser([
            'urn:oid:0.9.2342.19200300.100.1.3' => 'oid@example.com',
            'urn:oid:2.5.4.3' => 'OID User',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('oid@example.com', $attributes['email']);
        $this->assertEquals('OID User', $attributes['name']);
    }

    public function test_resolve_attributes_from_simple_names(): void
    {
        $user = $this->mockSamlUser([
            'email' => 'simple@example.com',
            'name' => 'Simple User',
            'first_name' => 'Simple',
            'last_name' => 'User',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('simple@example.com', $attributes['email']);
        $this->assertEquals('Simple User', $attributes['name']);
        $this->assertEquals('Simple', $attributes['first_name']);
        $this->assertEquals('User', $attributes['last_name']);
    }

    public function test_resolve_attributes_falls_back_to_socialite_email(): void
    {
        $user = $this->mockSamlUser([], 'fallback@example.com');

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('fallback@example.com', $attributes['email']);
    }

    public function test_resolve_attributes_falls_back_to_socialite_name(): void
    {
        $user = $this->mockSamlUser([], null, 'Fallback Name');

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('Fallback Name', $attributes['name']);
    }

    public function test_config_override_takes_priority_over_defaults(): void
    {
        config([
            'aicl.saml.attribute_map' => [
                'email' => ['custom_email_field'],
            ],
        ]);

        $user = $this->mockSamlUser([
            'custom_email_field' => 'custom@example.com',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'should-not-use@example.com',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('custom@example.com', $attributes['email']);
    }

    public function test_config_adds_custom_fields_beyond_defaults(): void
    {
        config([
            'aicl.saml.attribute_map' => [
                'department' => ['department', 'dept'],
                'employee_id' => ['employeeNumber'],
            ],
        ]);

        $user = $this->mockSamlUser([
            'email' => 'custom@example.com',
            'department' => 'Engineering',
            'employeeNumber' => 'EMP-001',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('Engineering', $attributes['department']);
        $this->assertEquals('EMP-001', $attributes['employee_id']);
    }

    public function test_resolve_roles_with_single_role_mapping(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'IT-Admins' => 'super_admin',
                    'Contractors' => 'viewer',
                ],
            ],
        ]);

        $user = $this->mockSamlUser(['groups' => 'IT-Admins']);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['super_admin'], $roles);
    }

    public function test_resolve_roles_with_multiple_groups(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'Managers' => 'admin',
                    'Engineering' => ['editor', 'api_user'],
                ],
            ],
        ]);

        $user = $this->mockSamlUser(['groups' => ['Managers', 'Engineering']]);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertContains('admin', $roles);
        $this->assertContains('editor', $roles);
        $this->assertContains('api_user', $roles);
    }

    public function test_resolve_roles_ignores_unmapped_groups(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'IT-Admins' => 'super_admin',
                ],
            ],
        ]);

        $user = $this->mockSamlUser(['groups' => ['IT-Admins', 'Unknown-Group']]);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['super_admin'], $roles);
    }

    public function test_resolve_roles_falls_back_to_default_when_no_map_matches(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'IT-Admins' => 'super_admin',
                ],
            ],
            'aicl.saml.default_role' => 'viewer',
        ]);

        $user = $this->mockSamlUser(['groups' => ['Unrecognized-Group']]);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['viewer'], $roles);
    }

    public function test_resolve_roles_falls_back_to_default_when_no_group_attribute(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'IT-Admins' => 'super_admin',
                ],
            ],
            'aicl.saml.default_role' => 'editor',
        ]);

        $user = $this->mockSamlUser(['email' => 'no-groups@example.com']);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['editor'], $roles);
    }

    public function test_resolve_roles_falls_back_to_default_when_map_is_empty(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [],
            ],
            'aicl.saml.default_role' => 'viewer',
        ]);

        $user = $this->mockSamlUser(['groups' => ['IT-Admins']]);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['viewer'], $roles);
    }

    public function test_resolve_roles_deduplicates_multiple_mappings(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'Group-A' => ['editor', 'viewer'],
                    'Group-B' => ['editor', 'admin'],
                ],
            ],
        ]);

        $user = $this->mockSamlUser(['groups' => ['Group-A', 'Group-B']]);

        $roles = $this->mapper->resolveRoles($user);

        // editor should only appear once
        $this->assertCount(1, array_keys($roles, 'editor'));
        $this->assertContains('viewer', $roles);
        $this->assertContains('admin', $roles);
    }

    public function test_resolve_roles_uses_custom_source_attribute(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'memberOf',
                'map' => [
                    'cn=admins,dc=example' => 'super_admin',
                ],
            ],
        ]);

        $user = $this->mockSamlUser(['memberOf' => 'cn=admins,dc=example']);

        $roles = $this->mapper->resolveRoles($user);

        $this->assertEquals(['super_admin'], $roles);
    }

    public function test_get_attribute_uses_alias_resolution(): void
    {
        $user = $this->mockSamlUser([
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'attr@example.com',
        ]);

        $email = $this->mapper->getAttribute($user, 'email');

        $this->assertEquals('attr@example.com', $email);
    }

    public function test_get_attribute_returns_null_for_unknown_field(): void
    {
        $user = $this->mockSamlUser([
            'email' => 'test@example.com',
        ]);

        $result = $this->mapper->getAttribute($user, 'nonexistent');

        $this->assertNull($result);
    }

    public function test_get_raw_attributes_normalizes_light_saml_attributes(): void
    {
        $user = $this->mockSamlUser([
            'email' => 'test@example.com',
            'groups' => ['Admin', 'Editor'],
        ]);

        $raw = $this->mapper->getRawAttributes($user);

        $this->assertEquals('test@example.com', $raw['email']);
        $this->assertEquals(['Admin', 'Editor'], $raw['groups']);
    }

    public function test_get_raw_attributes_returns_empty_array_when_raw_is_not_array(): void
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getRaw')->andReturn(null);

        $raw = $this->mapper->getRawAttributes($user);

        $this->assertEquals([], $raw);
    }

    public function test_custom_mapper_class_resolved_from_config(): void
    {
        config([
            'aicl.saml.mapper_class' => TestCustomSamlMapper::class,
        ]);

        $mapper = app(SamlAttributeMapper::class);

        $this->assertInstanceOf(TestCustomSamlMapper::class, $mapper);
    }

    public function test_default_mapper_used_when_no_custom_class(): void
    {
        config([
            'aicl.saml.mapper_class' => null,
        ]);

        $mapper = app(SamlAttributeMapper::class);

        $this->assertInstanceOf(SamlAttributeMapper::class, $mapper);
        $this->assertNotInstanceOf(TestCustomSamlMapper::class, $mapper);
    }

    public function test_first_matching_alias_wins(): void
    {
        $user = $this->mockSamlUser([
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'first@example.com',
            'email' => 'second@example.com',
        ]);

        $attributes = $this->mapper->resolveAttributes($user);

        $this->assertEquals('first@example.com', $attributes['email']);
    }
}

/**
 * Test custom mapper class for DI resolution tests.
 */
class TestCustomSamlMapper extends SamlAttributeMapper
{
    //
}
