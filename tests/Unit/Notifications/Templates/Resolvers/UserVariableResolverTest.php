<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Resolvers\UserVariableResolver;
use PHPUnit\Framework\TestCase;

class UserVariableResolverTest extends TestCase
{
    private UserVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UserVariableResolver;
    }

    public function test_implements_variable_resolver(): void
    {
        $this->assertInstanceOf(VariableResolver::class, $this->resolver);
    }

    public function test_resolves_user_field(): void
    {
        $user = (object) ['name' => 'Alice', 'email' => 'alice@example.com'];

        $result = $this->resolver->resolve('name', ['user' => $user]);

        $this->assertSame('Alice', $result);
    }

    public function test_resolves_email_field(): void
    {
        $user = (object) ['name' => 'Alice', 'email' => 'alice@example.com'];

        $result = $this->resolver->resolve('email', ['user' => $user]);

        $this->assertSame('alice@example.com', $result);
    }

    public function test_returns_null_when_no_user_in_context(): void
    {
        $result = $this->resolver->resolve('name', []);

        $this->assertNull($result);
    }

    public function test_returns_null_when_user_is_not_object(): void
    {
        $result = $this->resolver->resolve('name', ['user' => 'not an object']);

        $this->assertNull($result);
    }

    public function test_returns_null_for_nonexistent_field(): void
    {
        $user = (object) ['name' => 'Alice'];

        $result = $this->resolver->resolve('nonexistent', ['user' => $user]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_null_field_value(): void
    {
        $user = (object) ['name' => null];

        $result = $this->resolver->resolve('name', ['user' => $user]);

        $this->assertNull($result);
    }

    public function test_returns_integer_as_string(): void
    {
        $user = (object) ['id' => 42];

        $result = $this->resolver->resolve('id', ['user' => $user]);

        $this->assertSame('42', $result);
    }

    public function test_returns_null_for_non_scalar_field(): void
    {
        $user = (object) ['roles' => ['admin', 'editor']];

        $result = $this->resolver->resolve('roles', ['user' => $user]);

        $this->assertNull($result);
    }
}
