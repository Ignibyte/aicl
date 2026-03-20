<?php

declare(strict_types=1);

namespace Aicl\Mcp\Resources;

use Aicl\Services\EntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/** MCP resource that lists available entity types with record counts and field information. */
class EntityListResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    public function name(): string
    {
        return 'entity_list';
    }

    public function title(): string
    {
        return 'Entity List';
    }

    public function description(): string
    {
        return 'List available entity types with record counts and field information.';
    }

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('entity://{type}');
    }

    public function handle(Request $request): Response
    {
        $type = $request->get('type');

        /** @var EntityRegistry $registry */
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        if ($type) {
            $entry = $entities->first(
                fn (array $entry): bool => Str::snake(class_basename($entry['class'])) === $type
            );

            if (! $entry) {
                return Response::error("Entity type '{$type}' not found.");
            }

            return Response::json($this->formatEntry($entry));
        }

        $result = $entities->map(fn (array $entry): array => $this->formatEntry($entry))->values()->all();

        return Response::json($result);
    }

    /**
     * Format an entity registry entry for the resource response.
     *
     * @param  array{class: class-string, table: string, label: string, base_class: class-string|null, columns: array<string, bool>}  $entry
     * @return array<string, mixed>
     */
    protected function formatEntry(array $entry): array
    {
        /** @var Model $instance */
        $instance = new $entry['class'];

        return [
            'type' => Str::snake(class_basename($entry['class'])),
            'label' => $entry['label'],
            'table' => $entry['table'],
            'fillable_fields' => $instance->getFillable(),
            'columns' => $entry['columns'],
        ];
    }
}
