<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\AiToolRegistry;
use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Events\AiToolCallEvent;
use Aicl\AI\Jobs\AiStreamJob;
use Aicl\AI\Tools\WhosOnlineTool;
use Generator;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
use Mockery;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolCall;
use Tests\TestCase;

class AiToolCallStreamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    // --- AiToolCallEvent broadcast tests ---

    public function test_ai_tool_call_event_implements_should_broadcast_now(): void
    {
        $event = new AiToolCallEvent('stream-123', 1, [
            ['name' => 'whos_online', 'inputs' => []],
        ]);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_ai_tool_call_event_broadcasts_on_correct_channel(): void
    {
        $event = new AiToolCallEvent('abc-uuid', 1, []);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-ai.stream.abc-uuid', $channels[0]->name);
    }

    public function test_ai_tool_call_event_broadcast_as_returns_correct_name(): void
    {
        $event = new AiToolCallEvent('stream-1', 1, []);

        $this->assertSame('ai.tool_call', $event->broadcastAs());
    }

    public function test_ai_tool_call_event_broadcast_with_returns_expected_payload(): void
    {
        $tools = [
            ['name' => 'entity_count', 'inputs' => ['entity_type' => 'users']],
            ['name' => 'health_status', 'inputs' => []],
        ];

        $event = new AiToolCallEvent('stream-456', 1, $tools);

        $data = $event->broadcastWith();

        $this->assertSame('stream-456', $data['stream_id']);
        $this->assertCount(2, $data['tools']);
        $this->assertSame('entity_count', $data['tools'][0]['name']);
        $this->assertSame(['entity_type' => 'users'], $data['tools'][0]['inputs']);
        $this->assertSame('health_status', $data['tools'][1]['name']);
    }

    public function test_ai_tool_call_event_constructor_sets_properties(): void
    {
        $tools = [['name' => 'test_tool', 'inputs' => ['key' => 'val']]];
        $event = new AiToolCallEvent('my-stream', 42, $tools);

        $this->assertSame('my-stream', $event->streamId);
        $this->assertSame(42, $event->userId);
        $this->assertSame($tools, $event->tools);
    }

    // --- AiStreamJob tool wiring tests ---

    public function test_build_agent_wires_tools_when_enabled(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.tools_enabled' => true,
        ]);

        // Register a tool
        $registry = app(AiToolRegistry::class);
        $registry->register(WhosOnlineTool::class);

        $agentMock = Mockery::mock(AgentInterface::class);
        $agentMock->shouldReceive('stream')->once()->andReturnUsing(function (): Generator {
            yield 'Hello';

            return new AssistantMessage('Hello');
        });

        // We need to verify addTool was called — use a spy wrapper
        $addToolCalled = false;
        $originalTools = [];

        $job = new class('stream-1', 1, 'test', $agentMock, $addToolCalled, $originalTools) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            private bool $addToolRef;

            /** @var array<mixed> */
            private array $toolsRef;

            /**
             * @param  array<mixed>  $toolsRef
             */
            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent, bool &$addToolCalled, array &$originalTools)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
                $this->addToolRef = &$addToolCalled;
                $this->toolsRef = &$originalTools;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                // Call parent logic to test the tool wiring, then return our mock
                if (config('aicl.ai.tools_enabled', true)) {
                    $registry = app(AiToolRegistry::class);
                    $tools = $registry->resolve($this->userId);

                    if (! empty($tools)) {
                        $this->addToolRef = true;
                        $this->toolsRef = $tools;
                    }
                }

                return $this->mockAgent;
            }
        };

        $job->handle();

        $this->assertTrue($addToolCalled);
        $this->assertNotEmpty($originalTools);
        $this->assertInstanceOf(WhosOnlineTool::class, $originalTools[0]);
    }

    public function test_build_agent_skips_tools_when_disabled(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.tools_enabled' => false,
        ]);

        $registry = app(AiToolRegistry::class);
        $registry->register(WhosOnlineTool::class);

        $addToolCalled = false;

        $agentMock = Mockery::mock(AgentInterface::class);
        $agentMock->shouldReceive('stream')->once()->andReturnUsing(function (): Generator {
            yield 'Hello';

            return new AssistantMessage('Hello');
        });

        $job = new class('stream-1', 1, 'test', $agentMock, $addToolCalled) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            private bool $addToolRef;

            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent, bool &$addToolCalled)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
                $this->addToolRef = &$addToolCalled;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                if (config('aicl.ai.tools_enabled', true)) {
                    $this->addToolRef = true;
                }

                return $this->mockAgent;
            }
        };

        $job->handle();

        $this->assertFalse($addToolCalled);
    }

    public function test_stream_loop_broadcasts_tool_call_event_on_tool_call_message(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.tools_enabled' => false,
        ]);

        $toolCall = Mockery::mock(ToolCall::class);
        $toolCall->shouldReceive('getName')->andReturn('whos_online');
        $toolCall->shouldReceive('getInputs')->andReturn([]);

        $toolCallMessage = Mockery::mock(ToolCallMessage::class);
        $toolCallMessage->shouldReceive('getTools')->andReturn([$toolCall]);

        $usage = new Usage(10, 5);
        $response = new AssistantMessage('The answer');
        $response->setUsage($usage);

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('stream')->once()->andReturnUsing(function () use ($toolCallMessage, $response): Generator {
            yield $toolCallMessage;
            yield 'The answer';

            return $response;
        });

        $job = new class('stream-tcm', 1, 'test', $agent) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                return $this->mockAgent;
            }
        };

        $job->handle();

        Event::assertDispatched(AiStreamStarted::class);

        Event::assertDispatched(AiToolCallEvent::class, function (AiToolCallEvent $event): bool {
            return $event->streamId === 'stream-tcm'
                && $event->userId === 1
                && count($event->tools) === 1
                && $event->tools[0]['name'] === 'whos_online';
        });

        Event::assertDispatched(AiTokenEvent::class, function (AiTokenEvent $event): bool {
            return $event->token === 'The answer' && $event->index === 0;
        });

        Event::assertDispatched(AiStreamCompleted::class);
    }

    public function test_stream_loop_handles_multiple_tool_call_messages(): void
    {
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.tools_enabled' => false,
        ]);

        $toolCall1 = Mockery::mock(ToolCall::class);
        $toolCall1->shouldReceive('getName')->andReturn('entity_count');
        $toolCall1->shouldReceive('getInputs')->andReturn(['entity_type' => 'users']);

        $toolCallMessage1 = Mockery::mock(ToolCallMessage::class);
        $toolCallMessage1->shouldReceive('getTools')->andReturn([$toolCall1]);

        $toolCall2 = Mockery::mock(ToolCall::class);
        $toolCall2->shouldReceive('getName')->andReturn('health_status');
        $toolCall2->shouldReceive('getInputs')->andReturn([]);

        $toolCallMessage2 = Mockery::mock(ToolCallMessage::class);
        $toolCallMessage2->shouldReceive('getTools')->andReturn([$toolCall2]);

        $response = new AssistantMessage('Result');
        $response->setUsage(new Usage(20, 10));

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('stream')->once()->andReturnUsing(function () use ($toolCallMessage1, $toolCallMessage2, $response): Generator {
            yield $toolCallMessage1;
            yield $toolCallMessage2;
            yield 'Result';

            return $response;
        });

        $job = new class('stream-multi', 1, 'test', $agent) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                return $this->mockAgent;
            }
        };

        $job->handle();

        Event::assertDispatched(AiToolCallEvent::class, 2);
        Event::assertDispatched(AiTokenEvent::class, 1);
        Event::assertDispatched(AiStreamCompleted::class);
    }
}
