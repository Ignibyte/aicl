<?php

namespace Aicl\Tests\Unit\Http\Controllers;

use Aicl\AI\AiAssistantController;
use Aicl\Filament\Pages\Backups;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase
{
    // ─── AiAssistantController ─────────────────────────────────

    public function test_ai_assistant_controller_extends_controller(): void
    {
        $this->assertTrue((new \ReflectionClass(AiAssistantController::class))->isSubclassOf(Controller::class));
    }

    public function test_ai_assistant_controller_has_ask_method(): void
    {
        $this->assertTrue((new \ReflectionClass(AiAssistantController::class))->hasMethod('ask'));
    }

    public function test_ai_assistant_controller_ask_returns_json_response(): void
    {
        $ref = new \ReflectionMethod(AiAssistantController::class, 'ask');
        $returnType = $ref->getReturnType();

        $this->assertNotNull($returnType);
        /** @phpstan-ignore-next-line */
        $this->assertEquals(JsonResponse::class, $returnType->getName());
    }

    public function test_ai_assistant_controller_has_resolve_entity_context_method(): void
    {
        $ref = new \ReflectionClass(AiAssistantController::class);
        $this->assertTrue($ref->hasMethod('resolveEntityContext'));
    }

    // ─── Backups Page ──────────────────────────────────────────

    public function test_backups_extends_vendor_backups_page(): void
    {
        $this->assertTrue((new \ReflectionClass(Backups::class))->isSubclassOf(\ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups::class));
    }

    public function test_backups_has_navigation_sort(): void
    {
        $ref = new \ReflectionClass(Backups::class);
        $prop = $ref->getProperty('navigationSort');

        $this->assertEquals(3, $prop->getDefaultValue());
    }
}
