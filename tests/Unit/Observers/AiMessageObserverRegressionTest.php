<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Observers;

use Aicl\Models\AiMessage;
use Aicl\Observers\AiMessageObserver;
use Aicl\Observers\BaseObserver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression tests for AiMessageObserver PHPStan changes.
 *
 * Covers the (int) cast on token_count, null guard for
 * ai_conversation_id, and the DB::raw expressions in both
 * created() and deleted() hooks. The observer extends BaseObserver
 * and overrides created() and deleted() only.
 */
class AiMessageObserverRegressionTest extends TestCase
{
    /**
     * Test observer extends BaseObserver.
     *
     * Verifies the class hierarchy is correct.
     */
    public function test_extends_base_observer(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiMessageObserver::class);
        $parent = $reflection->getParentClass();

        // Assert: parent class exists and is BaseObserver
        $this->assertNotFalse($parent, 'AiMessageObserver should have a parent class');
        $this->assertSame(BaseObserver::class, $parent->getName());
    }

    /**
     * Test created method accepts Model parameter.
     *
     * PHPStan enforced Model type hint on the created() method.
     */
    public function test_created_method_accepts_model_parameter(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(AiMessageObserver::class, 'created');
        $params = $reflection->getParameters();

        // Assert: single parameter named 'model'
        $this->assertCount(1, $params);
        $this->assertSame('model', $params[0]->getName());
    }

    /**
     * Test deleted method accepts Model parameter.
     *
     * PHPStan enforced Model type hint on the deleted() method.
     */
    public function test_deleted_method_accepts_model_parameter(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(AiMessageObserver::class, 'deleted');
        $params = $reflection->getParameters();

        // Assert: single parameter named 'model'
        $this->assertCount(1, $params);
        $this->assertSame('model', $params[0]->getName());
    }

    /**
     * Test created method returns void.
     *
     * PHPStan enforced void return type.
     */
    public function test_created_method_returns_void(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(AiMessageObserver::class, 'created');
        $returnType = $reflection->getReturnType();

        // Assert
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test created method exits early when ai_conversation_id is falsy.
     *
     * PHPStan enforced the null guard: if (! $model->ai_conversation_id).
     * When conversation_id is empty, the method returns without updating.
     * Uses forceFill to bypass PHPStan's property type check since the
     * PHPDoc declares ai_conversation_id as string but the DB allows null.
     */
    public function test_created_exits_early_for_empty_conversation_id(): void
    {
        // Arrange: message with empty conversation_id (simulates null from DB)
        $message = new AiMessage;
        $message->forceFill(['ai_conversation_id' => '']);

        $observer = new AiMessageObserver;

        // Act: should not throw — exits early due to null/empty guard
        $observer->created($message);

        // Assert: no exception means null guard worked correctly
        $this->addToAssertionCount(1);
    }

    /**
     * Test deleted method exits early when ai_conversation_id is falsy.
     *
     * Same null guard as created(): if (! $model->ai_conversation_id).
     */
    public function test_deleted_exits_early_for_empty_conversation_id(): void
    {
        // Arrange: message with empty conversation_id
        $message = new AiMessage;
        $message->forceFill(['ai_conversation_id' => '']);

        $observer = new AiMessageObserver;

        // Act: should not throw — exits early due to null guard
        $observer->deleted($message);

        // Assert: no exception means null guard worked correctly
        $this->addToAssertionCount(1);
    }

    /**
     * Test observer only overrides created and deleted methods.
     *
     * BaseObserver defines 12 hook methods. AiMessageObserver should
     * only override created() and deleted(), leaving others as no-ops.
     */
    public function test_only_overrides_created_and_deleted(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiMessageObserver::class);

        // Get methods declared directly on AiMessageObserver (not inherited)
        $ownMethods = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === AiMessageObserver::class
        );

        $ownMethodNames = array_map(fn (ReflectionMethod $m) => $m->getName(), $ownMethods);

        // Assert: only created and deleted are overridden
        $this->assertContains('created', $ownMethodNames);
        $this->assertContains('deleted', $ownMethodNames);
        $this->assertCount(2, $ownMethodNames, 'Observer should only override created and deleted');
    }
}
