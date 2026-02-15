<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Resolvers\ModelVariableResolver;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ModelVariableResolverTest extends TestCase
{
    private ModelVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ModelVariableResolver;
    }

    public function test_implements_variable_resolver(): void
    {
        $this->assertInstanceOf(VariableResolver::class, $this->resolver);
    }

    public function test_resolves_model_attribute(): void
    {
        $model = $this->createMockModel(['title' => 'Test Title', 'name' => 'Test Name']);

        $result = $this->resolver->resolve('title', ['model' => $model]);

        $this->assertSame('Test Title', $result);
    }

    public function test_returns_null_when_no_model_in_context(): void
    {
        $result = $this->resolver->resolve('title', []);

        $this->assertNull($result);
    }

    public function test_returns_null_when_model_is_not_eloquent(): void
    {
        $result = $this->resolver->resolve('title', ['model' => 'not a model']);

        $this->assertNull($result);
    }

    public function test_returns_null_for_nonexistent_attribute(): void
    {
        $model = $this->createMockModel(['title' => 'Test Title']);

        $result = $this->resolver->resolve('nonexistent', ['model' => $model]);

        $this->assertNull($result);
    }

    public function test_relationship_traversal_via_dot_notation(): void
    {
        $related = $this->createMockModel(['name' => 'Related Name']);
        $model = $this->createMockModel(['assignee' => $related]);

        $result = $this->resolver->resolve('assignee.name', ['model' => $model]);

        $this->assertSame('Related Name', $result);
    }

    public function test_null_safety_in_relationship_traversal(): void
    {
        $model = $this->createMockModel(['assignee' => null]);

        $result = $this->resolver->resolve('assignee.name', ['model' => $model]);

        $this->assertNull($result);
    }

    public function test_does_not_execute_methods(): void
    {
        // The resolver should use getAttribute(), not method calls.
        // If 'delete' attribute is null, it should return null, not call delete().
        $model = $this->createMockModel([]);

        $result = $this->resolver->resolve('delete', ['model' => $model]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_non_scalar_attribute(): void
    {
        $model = $this->createMockModel(['data' => ['nested' => 'array']]);

        $result = $this->resolver->resolve('data', ['model' => $model]);

        $this->assertNull($result);
    }

    public function test_resolves_integer_attribute_as_string(): void
    {
        $model = $this->createMockModel(['count' => 42]);

        $result = $this->resolver->resolve('count', ['model' => $model]);

        $this->assertSame('42', $result);
    }

    /**
     * Create a mock Eloquent model that returns given attributes via getAttribute().
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createMockModel(array $attributes): Model
    {
        $model = $this->createMock(Model::class);
        $model->method('getAttribute')->willReturnCallback(
            fn (string $key) => $attributes[$key] ?? null,
        );

        return $model;
    }
}
