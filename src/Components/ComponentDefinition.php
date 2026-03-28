<?php

declare(strict_types=1);

namespace Aicl\Components;

/**
 * Readonly value object holding parsed component.json data.
 *
 * Each registered component (framework or client) is represented
 * by a ComponentDefinition instance in the ComponentRegistry.
 */
class ComponentDefinition
{
    /**
     * @param  array<int, string>  $context
     * @param  array<int, string>  $notFor
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $slots
     * @param  array<string, mixed>  $variants
     * @param  array<int, string>  $composableIn
     * @param  array<string, mixed>  $fieldSignals
     * @param  array<string, mixed>|null  $filamentEquivalent
     * @param  array<string, mixed>|null  $entityDisplay
     */
    public function __construct(
        public readonly string $name,
        public readonly string $tag,
        public readonly string $class,
        public readonly string $template,
        public readonly ?string $jsModule,
        public readonly string $category,
        public readonly string $status,
        public readonly string $description,
        public readonly array $context,
        public readonly array $notFor,
        public readonly array $props,
        public readonly array $slots,
        public readonly array $variants,
        public readonly array $composableIn,
        public readonly string $decisionRule,
        public readonly array $fieldSignals,
        public readonly ?array $filamentEquivalent,
        public readonly ?array $entityDisplay,
        public readonly string $source,
    ) {}

    /**
     * Create a ComponentDefinition from a parsed component.json array.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(array $manifest, string $class, string $template, ?string $jsModule, string $source): self
    {
        return new self(
            name: $manifest['name'],
            tag: $manifest['tag'],
            class: $class,
            template: $template,
            jsModule: $jsModule,
            category: $manifest['category'],
            status: $manifest['status'],
            description: $manifest['description'],
            context: $manifest['context'] ?? [],
            notFor: $manifest['not_for'] ?? [],
            props: $manifest['props'] ?? [],
            slots: $manifest['slots'] ?? [],
            variants: $manifest['variants'] ?? [],
            composableIn: $manifest['composable_in'] ?? [],
            decisionRule: $manifest['decision_rule'],
            fieldSignals: $manifest['field_signals'] ?? [],
            filamentEquivalent: $manifest['filament_equivalent'] ?? null,
            entityDisplay: $manifest['entity_display'] ?? null,
            source: $source,
        );
    }

    /**
     * Get the short tag name without the x-aicl- prefix.
     */
    public function shortTag(): string
    {
        return str_replace('x-aicl-', '', $this->tag);
    }

    /**
     * Check if this component is valid for a given rendering context.
     */
    public function supportsContext(string $context): bool
    {
        return in_array($context, $this->context, true);
    }

    /**
     * Check if this component is explicitly excluded from a context.
     */
    public function isExcludedFrom(string $context): bool
    {
        return in_array($context, $this->notFor, true);
    }

    /**
     * Get required prop names.
     *
     * @return array<int, string>
     */
    public function requiredProps(): array
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return array_keys(array_filter($this->props, fn (array $prop): bool => ($prop['required'] ?? false) === true));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Convert to array for caching.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tag' => $this->tag,
            'class' => $this->class,
            'template' => $this->template,
            'jsModule' => $this->jsModule,
            'category' => $this->category,
            'status' => $this->status,
            'description' => $this->description,
            'context' => $this->context,
            'notFor' => $this->notFor,
            'props' => $this->props,
            'slots' => $this->slots,
            'variants' => $this->variants,
            'composableIn' => $this->composableIn,
            'decisionRule' => $this->decisionRule,
            'fieldSignals' => $this->fieldSignals,
            'filamentEquivalent' => $this->filamentEquivalent,
            'entityDisplay' => $this->entityDisplay,
            'source' => $this->source,
        ];
    }

    /**
     * Restore from cached array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return new self(
            name: $data['name'],
            tag: $data['tag'],
            class: $data['class'],
            template: $data['template'],
            jsModule: $data['jsModule'],
            category: $data['category'],
            status: $data['status'],
            description: $data['description'],
            context: $data['context'],
            notFor: $data['notFor'],
            props: $data['props'],
            slots: $data['slots'],
            variants: $data['variants'],
            composableIn: $data['composableIn'],
            decisionRule: $data['decisionRule'],
            fieldSignals: $data['fieldSignals'],
            filamentEquivalent: $data['filamentEquivalent'],
            entityDisplay: $data['entityDisplay'] ?? null,
            source: $data['source'],
        );
        // @codeCoverageIgnoreEnd
    }
}
