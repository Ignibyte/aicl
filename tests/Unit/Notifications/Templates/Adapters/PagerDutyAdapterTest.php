<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\PagerDutyAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use Tests\TestCase;

class PagerDutyAdapterTest extends TestCase
{
    private PagerDutyAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new PagerDutyAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_pagerduty(): void
    {
        $this->assertSame(ChannelType::PagerDuty, $this->adapter->channelType());
    }

    public function test_produces_events_api_v2_payload(): void
    {
        $channel = (object) ['config' => ['routing_key' => 'test-routing-key']];

        $result = $this->adapter->format([
            'title' => 'Critical Alert',
            'body' => 'Server is down',
            'color' => 'danger',
        ], ['channel' => $channel]);

        $this->assertSame('test-routing-key', $result['routing_key']);
        $this->assertSame('trigger', $result['event_action']);
        $this->assertArrayHasKey('payload', $result);
    }

    public function test_payload_contains_summary(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert Title',
            'body' => 'Alert body',
        ], []);

        $this->assertSame('Alert Title: Alert body', $result['payload']['summary']);
    }

    public function test_payload_contains_severity(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'color' => 'danger',
        ], []);

        $this->assertSame('critical', $result['payload']['severity']);
    }

    public function test_maps_warning_severity(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'color' => 'warning',
        ], []);

        $this->assertSame('warning', $result['payload']['severity']);
    }

    public function test_maps_success_to_info_severity(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'color' => 'success',
        ], []);

        $this->assertSame('info', $result['payload']['severity']);
    }

    public function test_maps_unknown_color_to_info(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'color' => 'primary',
        ], []);

        $this->assertSame('info', $result['payload']['severity']);
    }

    public function test_default_color_maps_to_info(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertSame('info', $result['payload']['severity']);
    }

    public function test_payload_source_from_config(): void
    {
        config(['app.name' => 'TestApp']);

        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertSame('TestApp', $result['payload']['source']);
    }

    public function test_routing_key_from_channel_config(): void
    {
        $channel = (object) ['config' => ['routing_key' => 'my-key-123']];

        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], ['channel' => $channel]);

        $this->assertSame('my-key-123', $result['routing_key']);
    }

    public function test_empty_routing_key_without_channel(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertSame('', $result['routing_key']);
    }

    public function test_payload_contains_custom_details(): void
    {
        $rendered = [
            'title' => 'Alert',
            'body' => 'Body',
            'color' => 'info',
        ];

        $result = $this->adapter->format($rendered, []);

        $this->assertSame($rendered, $result['payload']['custom_details']);
    }

    public function test_payload_contains_component(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], ['entity_type' => 'server']);

        $this->assertSame('server', $result['payload']['component']);
    }

    public function test_default_component_is_application(): void
    {
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertSame('application', $result['payload']['component']);
    }
}
