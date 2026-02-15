<?php

namespace Aicl\Notifications\Templates;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Filters\RawFilter;

class MessageTemplateRenderer
{
    /** @var array<string, VariableResolver> */
    protected array $resolvers = [];

    public function __construct(
        protected FilterRegistry $filterRegistry,
        protected FormatAdapterRegistry $formatAdapterRegistry,
        protected bool $escapeHtml = true,
    ) {}

    /**
     * Register a variable resolver for a prefix.
     */
    public function registerResolver(string $prefix, VariableResolver $resolver): void
    {
        $this->resolvers[$prefix] = $resolver;
    }

    /**
     * Render a template string with context variables and filters.
     *
     * @param  string  $template  Template string with {{ variable | filter }} syntax
     * @param  array<string, mixed>  $context  Context data keyed by prefix
     * @return string Rendered output
     */
    public function render(string $template, array $context): string
    {
        return (string) preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($matches) use ($context) {
            return $this->resolveExpression($matches[1], $context);
        }, $template);
    }

    /**
     * Render a template array (title + body) with context, then format for a channel.
     *
     * @param  array{title: string, body: string}  $templates  Template strings
     * @param  array<string, mixed>  $context  Context data
     * @param  ChannelType  $channelType  Target channel type for formatting
     * @return array<string, mixed> Channel-formatted payload
     */
    public function renderForChannel(
        array $templates,
        array $context,
        ChannelType $channelType,
    ): array {
        $rendered = [
            'title' => $this->render($templates['title'], $context),
            'body' => $this->render($templates['body'], $context),
            'action_url' => $context['action_url'] ?? null,
            'action_text' => $context['action_text'] ?? null,
            'color' => $context['color'] ?? null,
        ];

        if ($this->formatAdapterRegistry->has($channelType)) {
            return $this->formatAdapterRegistry->resolve($channelType)->format($rendered, $context);
        }

        return $rendered;
    }

    /**
     * Resolve a template from a channel's message_templates for a notification class.
     *
     * Resolution order:
     * 1. Exact match on notification class name
     * 2. Fall back to '_default' key
     * 3. Return null (use notification's toDatabase() output)
     *
     * @return array{title: string, body: string}|null Template if found, null to use fallback
     */
    public function resolveTemplate(
        NotificationChannel $channel,
        string $notificationClass,
    ): ?array {
        $templates = $channel->message_templates ?? [];

        if (isset($templates[$notificationClass])) {
            return $templates[$notificationClass];
        }

        if (isset($templates['_default'])) {
            return $templates['_default'];
        }

        return null;
    }

    /**
     * Resolve a single {{ expression }} -- variable + filter chain.
     */
    protected function resolveExpression(string $expression, array $context): string
    {
        $parts = array_map('trim', explode('|', $expression));
        $variableRef = array_shift($parts);

        $value = $this->resolveVariable($variableRef, $context);

        $shouldEscape = $this->escapeHtml;
        $hasRawFilter = false;

        foreach ($parts as $filterExpr) {
            $filterName = trim(explode(':', $filterExpr)[0]);

            if ($this->filterRegistry->has($filterName)) {
                $filter = $this->filterRegistry->resolve($filterName);
                if ($filter instanceof RawFilter) {
                    $hasRawFilter = true;
                }
            }

            $value = $this->applyFilter($filterExpr, $value, $context);
        }

        if ($shouldEscape && ! $hasRawFilter) {
            $value = e($value);
        }

        return $value;
    }

    /**
     * Resolve a variable reference (e.g., 'model.title', 'user.name', 'app.name').
     */
    protected function resolveVariable(string $reference, array $context): string
    {
        if (str_contains($reference, '.')) {
            $prefix = substr($reference, 0, (int) strpos($reference, '.'));
            $field = substr($reference, strpos($reference, '.') + 1);

            if (isset($this->resolvers[$prefix])) {
                $resolved = $this->resolvers[$prefix]->resolve($field, $context);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        if (isset($context[$reference])) {
            return is_string($context[$reference]) ? $context[$reference] : (string) $context[$reference];
        }

        return '';
    }

    /**
     * Apply a single filter expression (e.g., 'truncate:50', 'upper').
     */
    protected function applyFilter(string $filterExpr, string $value, array $context): string
    {
        $colonPos = strpos($filterExpr, ':');

        if ($colonPos !== false) {
            $filterName = substr($filterExpr, 0, $colonPos);
            $argument = substr($filterExpr, $colonPos + 1);
        } else {
            $filterName = $filterExpr;
            $argument = null;
        }

        if ($this->filterRegistry->has($filterName)) {
            return $this->filterRegistry->resolve($filterName)->apply($value, $argument, $context);
        }

        return $value;
    }
}
