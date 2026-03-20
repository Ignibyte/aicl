<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use Illuminate\Support\Str;

/**
 * Resolves template variables in notification specs to PHP code.
 *
 * Supported variables:
 *   {model.name}          → $this->{snake}->name
 *   {model.field}         → $this->{snake}->field
 *   {actor.name}          → $this->changedBy->name (or auth()->user()->name)
 *   {old.status.label}    → $this->previousStatus->label()
 *   {new.status.label}    → $this->newStatus->label()
 *   new.status.color      → $this->newStatus->color() (for Color field, no braces)
 */
class NotificationTemplateResolver
{
    public function __construct(
        protected string $entityName,
    ) {}

    /**
     * Resolve template variables in a notification body string.
     *
     * Returns a PHP string expression suitable for embedding in generated code.
     */
    public function resolveBody(string $template): string
    {
        $snakeName = Str::snake($this->entityName);

        // Replace template variables with PHP interpolation expressions
        $result = $template;

        // {model.field} → {$this->{snake}->field}
        $result = preg_replace_callback(
            '/\{model\.(\w+)\}/',
            fn ($m) => "{\$this->{$snakeName}->{$m[1]}}",
            $result
        ) ?? $result;

        // {actor.name} → {$this->changedBy->name}
        $result = str_replace('{actor.name}', '{\$this->changedBy->name}', $result);

        // {old.status.label} → {$this->previousStatus->label()}
        $result = str_replace('{old.status.label}', '{\$this->previousStatus->label()}', $result);

        // {new.status.label} → {$this->newStatus->label()}
        $result = str_replace('{new.status.label}', '{\$this->newStatus->label()}', $result);

        // {old.field.label} → {$this->previousField->label()} (generic)
        $result = preg_replace_callback(
            '/\{old\.(\w+)\.label\}/',
            fn ($m) => '{\$this->previous'.Str::studly($m[1]).'->label()}',
            $result
        ) ?? $result;

        // {new.field.label} → {$this->newField->label()} (generic)
        $result = preg_replace_callback(
            '/\{new\.(\w+)\.label\}/',
            fn ($m) => '{\$this->new'.Str::studly($m[1]).'->label()}',
            $result
        ) ?? $result;

        return $result;
    }

    /**
     * Resolve a color value reference.
     *
     * Static colors return as string literal.
     * Dynamic references like "new.status.color" return PHP method calls.
     */
    public function resolveColor(string $colorRef): string
    {
        // Dynamic: "new.status.color" → $this->newStatus->color()
        if (preg_match('/^new\.(\w+)\.color$/', $colorRef, $m)) {
            return '$this->new'.Str::studly($m[1]).'->color()';
        }

        // Static color name
        return "'{$colorRef}'";
    }

    /**
     * Resolve a recipient reference to PHP code.
     *
     * "owner" → $model->owner
     * "assigned_to" → $model->{assigned_to relationship}
     * "{field_name}" → $model->{field_name}
     */
    public function resolveRecipient(string $recipient): string
    {
        $snakeName = Str::snake($this->entityName);

        return match ($recipient) {
            'owner' => "\${$snakeName}->owner",
            default => "\${$snakeName}->".Str::camel(str_replace('_id', '', $recipient)),
        };
    }
}
