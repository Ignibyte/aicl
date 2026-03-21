<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Notifications\Templates;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\FilterRegistry;
use Aicl\Notifications\Templates\FormatAdapterRegistry;
use Aicl\Notifications\Templates\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for MessageTemplateRenderer PHPStan changes.
 *
 * Covers the (int) cast on strpos(), string type hints on protected
 * methods, null coalescing in resolveTemplate(), and the escapeHtml
 * constructor parameter. Extends existing MessageTemplateRendererTest
 * with PHPStan-specific edge cases.
 */
class MessageTemplateRendererRegressionTest extends TestCase
{
    private FilterRegistry $filterRegistry;

    private FormatAdapterRegistry $formatAdapterRegistry;

    private MessageTemplateRenderer $renderer;

    /**
     * Set up test dependencies.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filterRegistry = new FilterRegistry;
        $this->formatAdapterRegistry = new FormatAdapterRegistry;
        $this->renderer = new MessageTemplateRenderer(
            $this->filterRegistry,
            $this->formatAdapterRegistry,
        );
    }

    /**
     * Test render returns string type.
     *
     * PHPStan enforced string return type annotation. The preg_replace_callback
     * uses (string) cast on the result.
     */
    public function test_render_returns_string(): void
    {
        // Act
        $result = $this->renderer->render('Hello world', []);

        // Assert
        $this->assertSame('Hello world', $result);
    }

    /**
     * Test render handles empty template.
     */
    public function test_render_handles_empty_template(): void
    {
        // Act
        $result = $this->renderer->render('', []);

        // Assert
        $this->assertSame('', $result);
    }

    /**
     * Test resolveVariable with dotless reference falls through to context.
     *
     * When the reference has no dot, it skips resolver lookup and
     * checks the context array directly.
     */
    public function test_resolve_variable_without_dot_uses_context(): void
    {
        // Arrange: context with a simple key (no dot prefix)
        $context = ['greeting' => 'Hello!'];

        // Act
        $result = $this->renderer->render('{{ greeting }}', $context);

        // Assert: resolved from context
        $this->assertStringContainsString('Hello!', $result);
    }

    /**
     * Test resolveVariable with non-string context value gets cast to string.
     *
     * PHPStan enforced the is_string check: is_string($context[$reference])
     * with (string) fallback.
     */
    public function test_resolve_variable_casts_non_string_context_value(): void
    {
        // Arrange: context with integer value
        $context = ['count' => 42];

        // Act
        $result = $this->renderer->render('Count: {{ count }}', $context);

        // Assert: integer cast to string
        $this->assertStringContainsString('42', $result);
    }

    /**
     * Test resolveVariable returns empty string for missing reference.
     *
     * When neither resolver nor context has the variable, returns ''.
     */
    public function test_resolve_variable_returns_empty_for_missing(): void
    {
        // Act
        $result = $this->renderer->render('Before {{ missing }} After', []);

        // Assert: variable resolves to empty string
        $this->assertSame('Before  After', $result);
    }

    /**
     * Test resolver returns null falls through to context lookup.
     *
     * When a resolver is registered but returns null for the field,
     * the code falls through to context lookup.
     */
    public function test_resolver_returning_null_falls_through(): void
    {
        // Arrange: resolver that always returns null
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return null;
            }
        });

        // Act: reference uses 'model.' prefix but resolver returns null
        $result = $this->renderer->render('{{ model.name }}', []);

        // Assert: falls through to context (empty) -> empty string
        $this->assertSame('', $result);
    }

    /**
     * Test escapeHtml=false disables default escaping.
     *
     * Constructor parameter escapeHtml controls whether output is escaped.
     */
    public function test_escape_html_false_disables_escaping(): void
    {
        // Arrange: renderer with escaping disabled
        $renderer = new MessageTemplateRenderer(
            $this->filterRegistry,
            $this->formatAdapterRegistry,
            escapeHtml: false,
        );

        $renderer->registerResolver('model', new class implements VariableResolver
        {
            /** @phpstan-ignore-next-line */
            public function resolve(string $field, array $context): ?string
            {
                return '<b>bold</b>';
            }
        });

        // Act
        $result = $renderer->render('{{ model.name }}', []);

        // Assert: HTML is NOT escaped
        $this->assertSame('<b>bold</b>', $result);
    }

    /**
     * Test escapeHtml=true escapes HTML by default.
     */
    public function test_escape_html_true_escapes_output(): void
    {
        // Arrange: default renderer (escapeHtml=true)
        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            /** @phpstan-ignore-next-line */
            public function resolve(string $field, array $context): ?string
            {
                return '<script>alert("xss")</script>';
            }
        });

        // Act
        $result = $this->renderer->render('{{ model.name }}', []);

        // Assert: HTML is escaped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * Test renderForChannel includes action_url and color from context.
     *
     * PHPStan enforced the null coalescing: $context['action_url'] ?? null.
     */
    public function test_render_for_channel_includes_context_fields(): void
    {
        // Arrange: context with action_url and color
        $context = [
            'action_url' => 'https://example.com/view',
            'action_text' => 'View Details',
            'color' => 'success',
        ];

        // Act: render without format adapter (falls through to raw output)
        $result = $this->renderer->renderForChannel(
            ['title' => 'Test', 'body' => 'Body'],
            $context,
            ChannelType::Email,
        );

        // Assert: context fields are included in output
        $this->assertSame('https://example.com/view', $result['action_url']);
        $this->assertSame('View Details', $result['action_text']);
        $this->assertSame('success', $result['color']);
    }

    /**
     * Test renderForChannel handles null optional context fields.
     *
     * When action_url, action_text, and color are missing from context,
     * they should be null in the output.
     */
    public function test_render_for_channel_handles_missing_optional_fields(): void
    {
        // Arrange: empty context
        $result = $this->renderer->renderForChannel(
            ['title' => 'Title', 'body' => 'Body'],
            [],
            ChannelType::Sms,
        );

        // Assert: optional fields are null
        $this->assertNull($result['action_url']);
        $this->assertNull($result['action_text']);
        $this->assertNull($result['color']);
    }

    /**
     * Test applyFilter with filter that has colon argument.
     *
     * PHPStan enforced strpos() checks. The filter expression
     * 'truncate:50' splits on colon to extract name and argument.
     */
    public function test_apply_filter_parses_colon_argument(): void
    {
        // Arrange: register a custom filter that uses the argument
        $this->filterRegistry->register('repeat', new class implements TemplateFilter
        {
            public function apply(string $value, ?string $argument, array $context): string
            {
                $count = (int) ($argument ?? 1);

                return str_repeat($value, $count);
            }
        });

        $this->renderer->registerResolver('model', new class implements VariableResolver
        {
            /** @phpstan-ignore-next-line */
            public function resolve(string $field, array $context): ?string
            {
                return 'ab';
            }
        });

        // Act
        $result = $this->renderer->render('{{ model.name | repeat:3 }}', []);

        // Assert: 'ab' repeated 3 times
        $this->assertStringContainsString('ababab', $result);
    }

    /**
     * Test multiple variable expressions in one template.
     */
    public function test_multiple_expressions_resolved_independently(): void
    {
        // Arrange
        $this->renderer->registerResolver('user', new class implements VariableResolver
        {
            public function resolve(string $field, array $context): ?string
            {
                return match ($field) {
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                    default => null,
                };
            }
        });

        // Act
        $result = $this->renderer->render(
            '{{ user.name }} ({{ user.email }})',
            [],
        );

        // Assert: both expressions resolved
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@example.com', $result);
    }
}
