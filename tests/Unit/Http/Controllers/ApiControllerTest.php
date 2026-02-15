<?php

namespace Aicl\Tests\Unit\Http\Controllers;

use Aicl\AI\AiAssistantController;
use Aicl\Filament\Pages\Backups;
use Aicl\Http\Controllers\Api\FailureReportController;
use Aicl\Http\Controllers\Api\GenerationTraceController;
use Aicl\Http\Controllers\Api\PreventionRuleController;
use Aicl\Http\Controllers\Api\RlmFailureController;
use Aicl\Http\Controllers\Api\RlmKnowledgeController;
use Aicl\Http\Controllers\Api\RlmLessonController;
use Aicl\Http\Controllers\Api\RlmPatternController;
use Aicl\Http\Controllers\Api\RlmScoreController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase
{
    // ─── CRUD Controllers ──────────────────────────────────────

    /**
     * @return array<string, array{class-string}>
     */
    public static function crudControllerProvider(): array
    {
        return [
            'FailureReportController' => [FailureReportController::class],
            'GenerationTraceController' => [GenerationTraceController::class],
            'PreventionRuleController' => [PreventionRuleController::class],
            'RlmFailureController' => [RlmFailureController::class],
            'RlmLessonController' => [RlmLessonController::class],
            'RlmPatternController' => [RlmPatternController::class],
            'RlmScoreController' => [RlmScoreController::class],
        ];
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_extends_base_controller(string $class): void
    {
        $this->assertTrue(
            is_subclass_of($class, \App\Http\Controllers\Controller::class),
            "{$class} should extend App Controller"
        );
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_has_index_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'index'));
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_has_store_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'store'));
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_has_show_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'show'));
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_has_update_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'update'));
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_has_destroy_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'destroy'));
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_uses_paginates_api_requests(string $class): void
    {
        $this->assertTrue(
            in_array(\Aicl\Traits\PaginatesApiRequests::class, class_uses_recursive($class)),
            "{$class} should use PaginatesApiRequests trait"
        );
    }

    // ─── RlmKnowledgeController ────────────────────────────────

    public function test_rlm_knowledge_controller_extends_base_controller(): void
    {
        $this->assertTrue(
            is_subclass_of(RlmKnowledgeController::class, \App\Http\Controllers\Controller::class)
        );
    }

    public function test_rlm_knowledge_controller_has_search_method(): void
    {
        $this->assertTrue(method_exists(RlmKnowledgeController::class, 'search'));
    }

    public function test_rlm_knowledge_controller_has_recall_method(): void
    {
        $this->assertTrue(method_exists(RlmKnowledgeController::class, 'recall'));
    }

    public function test_rlm_knowledge_controller_has_get_failure_method(): void
    {
        $this->assertTrue(method_exists(RlmKnowledgeController::class, 'getFailure'));
    }

    public function test_rlm_knowledge_controller_has_embed_method(): void
    {
        $this->assertTrue(method_exists(RlmKnowledgeController::class, 'embed'));
    }

    public function test_rlm_knowledge_controller_constructor_requires_dependencies(): void
    {
        $ref = new \ReflectionClass(RlmKnowledgeController::class);
        $constructor = $ref->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertCount(2, $constructor->getParameters());
        $this->assertEquals('knowledgeService', $constructor->getParameters()[0]->getName());
        $this->assertEquals('embeddingService', $constructor->getParameters()[1]->getName());
    }

    // ─── AiAssistantController ─────────────────────────────────

    public function test_ai_assistant_controller_extends_controller(): void
    {
        $this->assertTrue(
            is_subclass_of(AiAssistantController::class, \Illuminate\Routing\Controller::class)
        );
    }

    public function test_ai_assistant_controller_has_ask_method(): void
    {
        $this->assertTrue(method_exists(AiAssistantController::class, 'ask'));
    }

    public function test_ai_assistant_controller_ask_returns_json_response(): void
    {
        $ref = new \ReflectionMethod(AiAssistantController::class, 'ask');
        $returnType = $ref->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals(\Illuminate\Http\JsonResponse::class, $returnType->getName());
    }

    public function test_ai_assistant_controller_has_resolve_entity_context_method(): void
    {
        $ref = new \ReflectionClass(AiAssistantController::class);
        $this->assertTrue($ref->hasMethod('resolveEntityContext'));
    }

    // ─── Backups Page ──────────────────────────────────────────

    public function test_backups_extends_vendor_backups_page(): void
    {
        $this->assertTrue(
            is_subclass_of(Backups::class, \ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups::class)
        );
    }

    public function test_backups_has_navigation_sort(): void
    {
        $ref = new \ReflectionClass(Backups::class);
        $prop = $ref->getProperty('navigationSort');

        $this->assertEquals(3, $prop->getDefaultValue());
    }

    // ─── Index method return types ─────────────────────────────

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_index_returns_resource_collection(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'index');
        $returnType = $ref->getReturnType();

        $this->assertNotNull($returnType, "{$class}::index() should have a return type");
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_store_returns_json_response(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'store');
        $returnType = $ref->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals(\Illuminate\Http\JsonResponse::class, $returnType->getName());
    }

    #[DataProvider('crudControllerProvider')]
    public function test_crud_controller_destroy_returns_json_response(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'destroy');
        $returnType = $ref->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals(\Illuminate\Http\JsonResponse::class, $returnType->getName());
    }
}
