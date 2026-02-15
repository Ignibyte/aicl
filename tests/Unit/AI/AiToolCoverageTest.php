<?php

namespace Aicl\Tests\Unit\AI;

use Aicl\AI\AiAssistantRequest;
use Aicl\AI\AiProviderFactory;
use Aicl\AI\AiToolRegistry;
use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Events\AiToolCallEvent;
use Aicl\AI\Jobs\AiStreamJob;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\CurrentUserTool;
use Aicl\AI\Tools\EntityCountTool;
use Aicl\AI\Tools\HealthStatusTool;
use Aicl\AI\Tools\QueryEntityTool;
use Aicl\AI\Tools\WhosOnlineTool;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\FormRequest;
use NeuronAI\Tools\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiToolCoverageTest extends TestCase
{
    // =====================================================================
    // BaseTool — abstract class structure
    // =====================================================================

    public function test_base_tool_extends_neuron_tool(): void
    {
        $this->assertTrue(is_subclass_of(BaseTool::class, Tool::class));
    }

    public function test_base_tool_implements_ai_tool_interface(): void
    {
        $reflection = new \ReflectionClass(BaseTool::class);

        $this->assertTrue($reflection->implementsInterface(AiTool::class));
    }

    public function test_base_tool_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BaseTool::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_tool_default_category_is_general(): void
    {
        $tool = new class extends BaseTool
        {
            public function __construct()
            {
                parent::__construct(name: 'test', description: 'Test tool');
            }
        };

        $this->assertSame('general', $tool->category());
    }

    public function test_base_tool_default_requires_auth_is_false(): void
    {
        $tool = new class extends BaseTool
        {
            public function __construct()
            {
                parent::__construct(name: 'test', description: 'Test tool');
            }
        };

        $this->assertFalse($tool->requiresAuth());
    }

    public function test_base_tool_set_authenticated_user_returns_fluent_self(): void
    {
        $tool = new class extends BaseTool
        {
            public function __construct()
            {
                parent::__construct(name: 'test', description: 'Test tool');
            }
        };

        $result = $tool->setAuthenticatedUser(42);

        $this->assertSame($tool, $result);
    }

    public function test_base_tool_get_authenticated_user_id_returns_null_by_default(): void
    {
        $tool = new class extends BaseTool
        {
            public function __construct()
            {
                parent::__construct(name: 'test', description: 'Test tool');
            }
        };

        $this->assertNull($tool->getAuthenticatedUserId());
    }

    public function test_base_tool_get_authenticated_user_id_returns_set_value(): void
    {
        $tool = new class extends BaseTool
        {
            public function __construct()
            {
                parent::__construct(name: 'test', description: 'Test tool');
            }
        };

        $tool->setAuthenticatedUser(99);

        $this->assertSame(99, $tool->getAuthenticatedUserId());
    }

    // =====================================================================
    // All tool classes — existence, inheritance, name, description, category
    // =====================================================================

    #[DataProvider('toolClassProvider')]
    public function test_tool_class_exists(string $toolClass): void
    {
        $this->assertTrue(class_exists($toolClass), "{$toolClass} should exist");
    }

    #[DataProvider('toolClassProvider')]
    public function test_tool_extends_base_tool(string $toolClass): void
    {
        $this->assertTrue(
            is_subclass_of($toolClass, BaseTool::class),
            "{$toolClass} should extend BaseTool"
        );
    }

    #[DataProvider('toolClassProvider')]
    public function test_tool_implements_ai_tool_interface(string $toolClass): void
    {
        $tool = new $toolClass;

        $this->assertInstanceOf(AiTool::class, $tool);
    }

    #[DataProvider('toolClassProvider')]
    public function test_tool_has_non_empty_name(string $toolClass): void
    {
        $tool = new $toolClass;

        $name = $tool->getName();

        $this->assertIsString($name);
        $this->assertNotEmpty($name, "{$toolClass}::getName() should return a non-empty string");
    }

    #[DataProvider('toolClassProvider')]
    public function test_tool_has_non_empty_description(string $toolClass): void
    {
        $tool = new $toolClass;

        $description = $tool->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description, "{$toolClass}::getDescription() should return a non-empty string");
    }

    #[DataProvider('toolClassProvider')]
    public function test_tool_has_non_empty_category(string $toolClass): void
    {
        $tool = new $toolClass;

        $category = $tool->category();

        $this->assertIsString($category);
        $this->assertNotEmpty($category, "{$toolClass}::category() should return a non-empty string");
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function toolClassProvider(): array
    {
        return [
            'CurrentUserTool' => [CurrentUserTool::class],
            'EntityCountTool' => [EntityCountTool::class],
            'HealthStatusTool' => [HealthStatusTool::class],
            'QueryEntityTool' => [QueryEntityTool::class],
            'WhosOnlineTool' => [WhosOnlineTool::class],
        ];
    }

    // =====================================================================
    // Tool-specific name checks
    // =====================================================================

    public function test_current_user_tool_name_is_current_user(): void
    {
        $tool = new CurrentUserTool;

        $this->assertSame('current_user', $tool->getName());
    }

    public function test_entity_count_tool_name_is_entity_count(): void
    {
        $tool = new EntityCountTool;

        $this->assertSame('entity_count', $tool->getName());
    }

    public function test_health_status_tool_name_is_health_status(): void
    {
        $tool = new HealthStatusTool;

        $this->assertSame('health_status', $tool->getName());
    }

    public function test_query_entity_tool_name_is_query_entity(): void
    {
        $tool = new QueryEntityTool;

        $this->assertSame('query_entity', $tool->getName());
    }

    public function test_whos_online_tool_name_is_whos_online(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertSame('whos_online', $tool->getName());
    }

    // =====================================================================
    // Tool-specific category checks
    // =====================================================================

    public function test_current_user_tool_category_is_system(): void
    {
        $tool = new CurrentUserTool;

        $this->assertSame('system', $tool->category());
    }

    public function test_entity_count_tool_category_is_queries(): void
    {
        $tool = new EntityCountTool;

        $this->assertSame('queries', $tool->category());
    }

    public function test_health_status_tool_category_is_system(): void
    {
        $tool = new HealthStatusTool;

        $this->assertSame('system', $tool->category());
    }

    public function test_query_entity_tool_category_is_queries(): void
    {
        $tool = new QueryEntityTool;

        $this->assertSame('queries', $tool->category());
    }

    public function test_whos_online_tool_category_is_system(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertSame('system', $tool->category());
    }

    // =====================================================================
    // Tool-specific requiresAuth checks
    // =====================================================================

    public function test_current_user_tool_requires_auth(): void
    {
        $this->assertTrue((new CurrentUserTool)->requiresAuth());
    }

    public function test_entity_count_tool_does_not_require_auth(): void
    {
        $this->assertFalse((new EntityCountTool)->requiresAuth());
    }

    public function test_health_status_tool_does_not_require_auth(): void
    {
        $this->assertFalse((new HealthStatusTool)->requiresAuth());
    }

    public function test_query_entity_tool_requires_auth(): void
    {
        $this->assertTrue((new QueryEntityTool)->requiresAuth());
    }

    public function test_whos_online_tool_does_not_require_auth(): void
    {
        $this->assertFalse((new WhosOnlineTool)->requiresAuth());
    }

    // =====================================================================
    // Tool-specific properties (parameters) checks
    // =====================================================================

    public function test_entity_count_tool_has_two_properties(): void
    {
        $tool = new EntityCountTool;
        $properties = $tool->getProperties();

        $this->assertCount(2, $properties);

        $names = array_map(fn ($p) => $p->getName(), $properties);
        $this->assertContains('entity_type', $names);
        $this->assertContains('group_by_status', $names);
    }

    public function test_query_entity_tool_has_three_properties(): void
    {
        $tool = new QueryEntityTool;
        $properties = $tool->getProperties();

        $this->assertCount(3, $properties);

        $names = array_map(fn ($p) => $p->getName(), $properties);
        $this->assertContains('entity_type', $names);
        $this->assertContains('filters', $names);
        $this->assertContains('limit', $names);
    }

    public function test_query_entity_tool_entity_type_is_required(): void
    {
        $tool = new QueryEntityTool;
        $required = $tool->getRequiredProperties();

        $this->assertContains('entity_type', $required);
    }

    public function test_entity_count_tool_has_no_required_properties(): void
    {
        $tool = new EntityCountTool;
        $required = $tool->getRequiredProperties();

        $this->assertEmpty($required);
    }

    public function test_current_user_tool_has_no_properties(): void
    {
        $tool = new CurrentUserTool;
        $properties = $tool->getProperties();

        $this->assertEmpty($properties);
    }

    public function test_health_status_tool_has_no_properties(): void
    {
        $tool = new HealthStatusTool;
        $properties = $tool->getProperties();

        $this->assertEmpty($properties);
    }

    public function test_whos_online_tool_has_no_properties(): void
    {
        $tool = new WhosOnlineTool;
        $properties = $tool->getProperties();

        $this->assertEmpty($properties);
    }

    // =====================================================================
    // AiAssistantRequest — form request structure
    // =====================================================================

    public function test_ai_assistant_request_extends_form_request(): void
    {
        $this->assertTrue(
            is_subclass_of(AiAssistantRequest::class, FormRequest::class)
        );
    }

    public function test_ai_assistant_request_has_rules_method(): void
    {
        $reflection = new \ReflectionClass(AiAssistantRequest::class);

        $this->assertTrue($reflection->hasMethod('rules'));
    }

    public function test_ai_assistant_request_has_authorize_method(): void
    {
        $reflection = new \ReflectionClass(AiAssistantRequest::class);

        $this->assertTrue($reflection->hasMethod('authorize'));
    }

    public function test_ai_assistant_request_has_messages_method(): void
    {
        $reflection = new \ReflectionClass(AiAssistantRequest::class);

        $this->assertTrue($reflection->hasMethod('messages'));
    }

    public function test_ai_assistant_request_rules_include_prompt(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('prompt', $rules);
    }

    public function test_ai_assistant_request_rules_include_entity_type(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_type', $rules);
    }

    public function test_ai_assistant_request_rules_include_entity_id(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('entity_id', $rules);
    }

    public function test_ai_assistant_request_rules_include_system_prompt(): void
    {
        $request = new AiAssistantRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('system_prompt', $rules);
    }

    public function test_ai_assistant_request_messages_has_custom_error_messages(): void
    {
        $request = new AiAssistantRequest;
        $messages = $request->messages();

        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey('prompt.required', $messages);
        $this->assertArrayHasKey('prompt.max', $messages);
        $this->assertArrayHasKey('entity_id.required_with', $messages);
    }

    // =====================================================================
    // AiStreamJob — queue job structure
    // =====================================================================

    public function test_ai_stream_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(AiStreamJob::class))
        );
    }

    public function test_ai_stream_job_uses_dispatchable(): void
    {
        $traits = class_uses_recursive(AiStreamJob::class);

        $this->assertContains(\Illuminate\Foundation\Bus\Dispatchable::class, $traits);
    }

    public function test_ai_stream_job_uses_queueable(): void
    {
        $traits = class_uses_recursive(AiStreamJob::class);

        $this->assertContains(Queueable::class, $traits);
    }

    public function test_ai_stream_job_uses_interacts_with_queue(): void
    {
        $traits = class_uses_recursive(AiStreamJob::class);

        $this->assertContains(\Illuminate\Queue\InteractsWithQueue::class, $traits);
    }

    public function test_ai_stream_job_can_be_instantiated(): void
    {
        $job = new AiStreamJob(
            streamId: 'test-stream-id',
            userId: 1,
            prompt: 'Hello world',
        );

        $this->assertSame('test-stream-id', $job->streamId);
        $this->assertSame(1, $job->userId);
        $this->assertSame('Hello world', $job->prompt);
    }

    public function test_ai_stream_job_accepts_optional_system_prompt(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
            systemPrompt: 'Be helpful',
        );

        $this->assertSame('Be helpful', $job->systemPrompt);
    }

    public function test_ai_stream_job_accepts_optional_context(): void
    {
        $context = ['entity' => 'User', 'id' => '42'];

        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
            context: $context,
        );

        $this->assertSame($context, $job->context);
    }

    public function test_ai_stream_job_accepts_optional_driver(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
            driver: 'anthropic',
        );

        $this->assertSame('anthropic', $job->driver);
    }

    public function test_ai_stream_job_defaults_to_single_try(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
        );

        $this->assertSame(1, $job->tries);
    }

    public function test_ai_stream_job_default_context_is_empty_array(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
        );

        $this->assertSame([], $job->context);
    }

    public function test_ai_stream_job_default_system_prompt_is_null(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
        );

        $this->assertNull($job->systemPrompt);
    }

    public function test_ai_stream_job_default_driver_is_null(): void
    {
        $job = new AiStreamJob(
            streamId: 'stream-1',
            userId: 1,
            prompt: 'Hello',
        );

        $this->assertNull($job->driver);
    }

    // =====================================================================
    // AI Events — broadcast structure
    // =====================================================================

    #[DataProvider('broadcastEventProvider')]
    public function test_ai_event_implements_should_broadcast_now(string $eventClass): void
    {
        $this->assertTrue(
            in_array(ShouldBroadcastNow::class, class_implements($eventClass)),
            "{$eventClass} should implement ShouldBroadcastNow"
        );
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function broadcastEventProvider(): array
    {
        return [
            'AiStreamStarted' => [AiStreamStarted::class],
            'AiStreamCompleted' => [AiStreamCompleted::class],
            'AiStreamFailed' => [AiStreamFailed::class],
            'AiTokenEvent' => [AiTokenEvent::class],
            'AiToolCallEvent' => [AiToolCallEvent::class],
        ];
    }

    public function test_ai_stream_started_broadcasts_on_private_channel(): void
    {
        $event = new AiStreamStarted('stream-123', 1);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_ai_stream_started_broadcast_as_returns_ai_started(): void
    {
        $event = new AiStreamStarted('stream-123', 1);

        $this->assertSame('ai.started', $event->broadcastAs());
    }

    public function test_ai_stream_started_broadcast_with_contains_stream_id(): void
    {
        $event = new AiStreamStarted('stream-abc', 1);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('stream_id', $data);
        $this->assertSame('stream-abc', $data['stream_id']);
    }

    public function test_ai_tool_call_event_broadcast_as_returns_ai_tool_call(): void
    {
        $event = new AiToolCallEvent('stream-1', 1, [
            ['name' => 'current_user', 'inputs' => []],
        ]);

        $this->assertSame('ai.tool_call', $event->broadcastAs());
    }

    public function test_ai_tool_call_event_broadcast_with_contains_tools(): void
    {
        $tools = [
            ['name' => 'entity_count', 'inputs' => ['entity_type' => 'users']],
        ];
        $event = new AiToolCallEvent('stream-1', 1, $tools);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('stream_id', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertSame($tools, $data['tools']);
    }

    public function test_ai_tool_call_event_stores_constructor_properties(): void
    {
        $tools = [['name' => 'health_status', 'inputs' => []]];
        $event = new AiToolCallEvent('stream-xyz', 42, $tools);

        $this->assertSame('stream-xyz', $event->streamId);
        $this->assertSame(42, $event->userId);
        $this->assertSame($tools, $event->tools);
    }

    // =====================================================================
    // AiProviderFactory — isConfigured returns false for unknown drivers
    // =====================================================================

    public function test_ai_provider_factory_is_configured_returns_false_for_unknown_driver(): void
    {
        $this->assertFalse(AiProviderFactory::isConfigured('nonexistent'));
    }

    // =====================================================================
    // AiToolRegistry — contract interface
    // =====================================================================

    public function test_ai_tool_contract_defines_category_method(): void
    {
        $reflection = new \ReflectionClass(AiTool::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('category'));
    }

    public function test_ai_tool_contract_defines_requires_auth_method(): void
    {
        $reflection = new \ReflectionClass(AiTool::class);

        $this->assertTrue($reflection->hasMethod('requiresAuth'));
    }
}
