<?php

namespace Aicl\Tests\Unit\Notifications\Templates;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\FilterRegistry;
use Aicl\Notifications\Templates\Filters\LowerFilter;
use Aicl\Notifications\Templates\Filters\RawFilter;
use Aicl\Notifications\Templates\Filters\TruncateFilter;
use Aicl\Notifications\Templates\Filters\UpperFilter;
use Aicl\Notifications\Templates\FormatAdapterRegistry;
use Aicl\Notifications\Templates\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

class MessageTemplateRendererTest extends TestCase
{
    private FilterRegistry $filterRegistry;

    private FormatAdapterRegistry $formatAdapterRegistry;

    private MessageTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterRegistry = new FilterRegistry;
        $this->formatAdapterRegistry = new FormatAdapterRegistry;
        $this->renderer = new MessageTemplateRenderer($this->filterRegistry, $this->formatAdapterRegistry);
    }

    // ── Basic variable interpolation ────────────────────────────

    public function test_basic_variable_interpolation(): void
    {
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                $model = $context['model'] ?? null;

                return $model[$field] ?? null;
            }
        });

        $result = $this->renderer->render(
            'Hello {{ model.title }}!',
            ['model' => ['title' => 'Test Item']],
        );

        $this->assertSame('Hello Test Item!', $result);
    }

    public function test_dot_notation_traversal(): void
    {
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                if ($field === 'relation.field') {
                    return 'nested_value';
                }

                return null;
            }
        });

        $result = $this->renderer->render(
            '{{ model.relation.field }}',
            [],
        );

        $this->assertSame('nested_value', $result);
    }

    public function test_filter_application(): void
    {
        $this->filterRegistry->register('upper', new UpperFilter);

        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return 'hello world';
            }
        });

        $result = $this->renderer->render(
            '{{ model.name | upper }}',
            [],
        );

        $this->assertSame('HELLO WORLD', $result);
    }

    public function test_chained_filters(): void
    {
        $this->filterRegistry->register('lower', new LowerFilter);
        $this->filterRegistry->register('truncate', new TruncateFilter);

        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return 'HELLO WORLD ABCDEF';
            }
        });

        $result = $this->renderer->render(
            '{{ model.name | lower | truncate:10 }}',
            [],
        );

        $this->assertSame('hello worl...', $result);
    }

    public function test_unknown_variable_resolves_to_empty_string(): void
    {
        $result = $this->renderer->render(
            'Hello {{ unknown.field }}!',
            [],
        );

        $this->assertSame('Hello !', $result);
    }

    public function test_unknown_filter_passes_value_through(): void
    {
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return 'test_value';
            }
        });

        $result = $this->renderer->render(
            '{{ model.name | nonexistent_filter }}',
            [],
        );

        // Value passes through unchanged (though may be escaped)
        $this->assertStringContainsString('test_value', $result);
    }

    // ── resolveTemplate() ───────────────────────────────────────

    public function test_resolve_template_exact_class_match(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [
            'App\\Notifications\\TestNotification' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
        ];

        $result = $this->renderer->resolveTemplate($channel, 'App\\Notifications\\TestNotification');

        $this->assertSame('Test Title', $result['title']);
        $this->assertSame('Test Body', $result['body']);
    }

    public function test_resolve_template_falls_back_to_default(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [
            '_default' => [
                'title' => 'Default Title',
                'body' => 'Default Body',
            ],
        ];

        $result = $this->renderer->resolveTemplate($channel, 'App\\Notifications\\Unknown');

        $this->assertSame('Default Title', $result['title']);
        $this->assertSame('Default Body', $result['body']);
    }

    public function test_resolve_template_returns_null_when_no_template(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = [];

        $result = $this->renderer->resolveTemplate($channel, 'App\\Notifications\\Unknown');

        $this->assertNull($result);
    }

    public function test_resolve_template_returns_null_when_templates_are_null(): void
    {
        $channel = new NotificationChannel;
        $channel->message_templates = null;

        $result = $this->renderer->resolveTemplate($channel, 'App\\Notifications\\Unknown');

        $this->assertNull($result);
    }

    // ── renderForChannel() ──────────────────────────────────────

    public function test_render_for_channel_end_to_end(): void
    {
        $mockAdapter = new class implements ChannelFormatAdapter
        {
            public function format(array $rendered, array $context): array
            {
                return [
                    'formatted_title' => strtoupper($rendered['title']),
                    'formatted_body' => $rendered['body'],
                ];
            }

            public function channelType(): ChannelType
            {
                return ChannelType::Webhook;
            }
        };

        $this->formatAdapterRegistry->register(ChannelType::Webhook, $mockAdapter);

        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return 'resolved_value';
            }
        });

        $result = $this->renderer->renderForChannel(
            ['title' => 'Title: {{ model.name }}', 'body' => 'Body: {{ model.name }}'],
            ['action_url' => 'https://example.com'],
            ChannelType::Webhook,
        );

        $this->assertSame('TITLE: RESOLVED_VALUE', $result['formatted_title']);
        $this->assertStringContainsString('resolved_value', $result['formatted_body']);
    }

    public function test_render_for_channel_without_registered_adapter(): void
    {
        $result = $this->renderer->renderForChannel(
            ['title' => 'Simple Title', 'body' => 'Simple Body'],
            ['action_url' => 'https://example.com', 'color' => 'primary'],
            ChannelType::Slack,
        );

        $this->assertSame('Simple Title', $result['title']);
        $this->assertSame('Simple Body', $result['body']);
        $this->assertSame('https://example.com', $result['action_url']);
        $this->assertSame('primary', $result['color']);
    }

    // ── HTML escaping ───────────────────────────────────────────

    public function test_html_escaping_by_default(): void
    {
        // config() returns null in unit tests, so escape_html defaults to true
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return '<script>alert("xss")</script>';
            }
        });

        $result = $this->renderer->render('{{ model.name }}', []);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function test_raw_filter_disables_escaping(): void
    {
        $this->filterRegistry->register('raw', new RawFilter);

        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return '<b>bold</b>';
            }
        });

        $result = $this->renderer->render('{{ model.name | raw }}', []);

        $this->assertSame('<b>bold</b>', $result);
    }

    // ── Custom resolver registration ────────────────────────────

    public function test_custom_resolver_registration(): void
    {
        $customResolver = new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                if ($field === 'greeting') {
                    return 'Welcome!';
                }

                return null;
            }
        };

        $this->renderer->registerResolver('custom', $customResolver);

        $result = $this->renderer->render('{{ custom.greeting }}', []);

        $this->assertStringContainsString('Welcome!', $result);
    }

    // ── Context variable fallback ───────────────────────────────

    public function test_context_variable_without_prefix_resolver(): void
    {
        $result = $this->renderer->render(
            '{{ title }} - {{ body }}',
            ['title' => 'My Title', 'body' => 'My Body'],
        );

        $this->assertStringContainsString('My Title', $result);
        $this->assertStringContainsString('My Body', $result);
    }

    // ── Multiple expressions ────────────────────────────────────

    public function test_multiple_expressions_in_single_template(): void
    {
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return match ($field) {
                    'name' => 'Alice',
                    'role' => 'Admin',
                    default => null,
                };
            }
        });

        $result = $this->renderer->render(
            '{{ model.name }} is {{ model.role }}',
            [],
        );

        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('Admin', $result);
    }

    // ── No expressions ──────────────────────────────────────────

    public function test_template_without_expressions_passes_through(): void
    {
        $result = $this->renderer->render('Plain text without variables', []);

        $this->assertSame('Plain text without variables', $result);
    }
}
