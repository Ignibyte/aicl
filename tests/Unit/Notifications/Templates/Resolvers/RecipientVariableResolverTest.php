<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Resolvers\RecipientVariableResolver;
use PHPUnit\Framework\TestCase;

class RecipientVariableResolverTest extends TestCase
{
    private RecipientVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RecipientVariableResolver;
    }

    public function test_implements_variable_resolver(): void
    {
        $this->assertInstanceOf(VariableResolver::class, $this->resolver);
    }

    public function test_resolves_recipient_field(): void
    {
        $recipient = (object) ['name' => 'Bob', 'email' => 'bob@example.com'];

        $result = $this->resolver->resolve('name', ['recipient' => $recipient]);

        $this->assertSame('Bob', $result);
    }

    public function test_resolves_email_field(): void
    {
        $recipient = (object) ['name' => 'Bob', 'email' => 'bob@example.com'];

        $result = $this->resolver->resolve('email', ['recipient' => $recipient]);

        $this->assertSame('bob@example.com', $result);
    }

    public function test_returns_null_when_no_recipient_in_context(): void
    {
        $result = $this->resolver->resolve('name', []);

        $this->assertNull($result);
    }

    public function test_returns_null_when_recipient_is_not_object(): void
    {
        $result = $this->resolver->resolve('name', ['recipient' => 'not an object']);

        $this->assertNull($result);
    }

    public function test_returns_null_for_nonexistent_field(): void
    {
        $recipient = (object) ['name' => 'Bob'];

        $result = $this->resolver->resolve('nonexistent', ['recipient' => $recipient]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_null_field_value(): void
    {
        $recipient = (object) ['name' => null];

        $result = $this->resolver->resolve('name', ['recipient' => $recipient]);

        $this->assertNull($result);
    }

    public function test_returns_integer_as_string(): void
    {
        $recipient = (object) ['id' => 99];

        $result = $this->resolver->resolve('id', ['recipient' => $recipient]);

        $this->assertSame('99', $result);
    }
}
