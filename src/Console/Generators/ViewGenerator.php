<?php

declare(strict_types=1);

namespace Aicl\Console\Generators;

use Aicl\Components\ComponentRegistry;
use Aicl\Console\Support\FieldDefinition;
use Illuminate\Support\Str;

/**
 * Generates public-facing Blade views for an entity using AICL components.
 *
 * Queries the ComponentRegistry to select appropriate components based on
 * entity field patterns. Generates: index, show, card component, filters
 * component, ViewController, and web routes.
 *
 * @codeCoverageIgnore Code generation command
 */
class ViewGenerator extends BaseGenerator
{
    public function label(): string
    {
        return "Creating views for: {$this->ctx->name}";
    }

    public function generate(): array
    {
        $files = [];
        $name = $this->ctx->name;
        $snake = $this->ctx->snakeName();
        $plural = Str::plural($snake);

        // Blade views
        $viewDir = resource_path("views/{$snake}");
        $this->ensureDirectoryExists($viewDir);
        $this->ensureDirectoryExists("{$viewDir}/components");

        $files[] = $this->generateIndexView($viewDir, $name, $snake, $plural);
        $files[] = $this->generateShowView($viewDir, $name, $snake);
        $files[] = $this->generateCardComponent($viewDir, $name, $snake);
        $files[] = $this->generateFiltersComponent($viewDir, $name, $snake);

        // Controller
        $files[] = $this->generateViewController($name, $snake, $plural);

        // Web routes
        $files[] = $this->generateWebRoutes($name, $snake, $plural);

        return $files;
    }

    private function generateIndexView(string $viewDir, string $name, string $snake, string $plural): string
    {
        $title = Str::headline($name);
        $pluralTitle = Str::headline(Str::plural($name));

        $hasStatus = $this->hasStatusField();
        $statusFilter = $hasStatus ? $this->buildStatusFilterHtml($snake) : '';

        $content = <<<BLADE
@extends('layouts.app')

@section('title', '{$pluralTitle}')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-white">{$pluralTitle}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your {$plural}.</p>
        </div>
        <a href="{{ route('{$plural}.create') }}"
           class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            New {$title}
        </a>
    </div>

{$statusFilter}
    {{-- Data Table --}}
    <x-aicl-data-table
        :columns="\$columns"
        :data="\$tableData"
        :sortable="true"
        :filterable="true"
        :paginated="true"
        :per-page="10"
        :selectable="false"
        empty-message="No {$plural} found."
    >
        <x-slot:empty>
            <x-aicl-empty-state
                title="No {$pluralTitle} Yet"
                description="Get started by creating your first {$snake}."
                action-label="Create {$title}"
                action-url="{{ route('{$plural}.create') }}"
                icon="heroicon-o-plus-circle"
            />
        </x-slot:empty>
    </x-aicl-data-table>
</div>
@endsection
BLADE;

        $path = "{$viewDir}/index.blade.php";
        file_put_contents($path, $content);

        return "resources/views/{$snake}/index.blade.php";
    }

    private function generateShowView(string $viewDir, string $name, string $snake): string
    {
        $title = Str::headline($name);
        $plural = Str::plural($snake);
        $displayField = $this->ctx->getDisplayField();

        $hasStatus = $this->hasStatusField();
        $statusBadge = $hasStatus
            ? "\n            <x-aicl-status-badge :status=\"\${$snake}->status\" />"
            : '';

        $content = <<<BLADE
@extends('layouts.app')

@section('title', \${$snake}->{$displayField})

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Breadcrumb --}}
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('{$plural}.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400">{$title}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900 dark:text-white">{{ \${$snake}->{$displayField} }}</span>
    </nav>

    <x-aicl-split-layout>
        <x-slot:main>
            {{-- Header --}}
            <div class="mb-6">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-white">{{ \${$snake}->{$displayField} }}</h1>{$statusBadge}
                </div>
            </div>

            {{-- Content --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                @if(\${$snake}->description ?? null)
                    <div class="prose dark:prose-invert max-w-none">
                        {!! \${$snake}->description !!}
                    </div>
                @endif
            </div>
        </x-slot:main>

        <x-slot:sidebar>
            {{-- Metadata --}}
            <x-aicl-info-card title="Details">
                <x-aicl-metadata-list :items="\$metadata" />
            </x-aicl-info-card>

            {{-- Actions --}}
            <x-aicl-action-bar class="mt-4">
                <x-aicl-quick-action
                    label="Edit"
                    icon="heroicon-o-pencil-square"
                    :url="route('{$plural}.edit', \${$snake})"
                />
            </x-aicl-action-bar>
        </x-slot:sidebar>
    </x-aicl-split-layout>
</div>
@endsection
BLADE;

        $path = "{$viewDir}/show.blade.php";
        file_put_contents($path, $content);

        return "resources/views/{$snake}/show.blade.php";
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    private function generateCardComponent(string $viewDir, string $name, string $snake): string
    {
        $plural = Str::plural($snake);
        $displayField = $this->ctx->getDisplayField();

        $hasStatus = $this->hasStatusField();
        $statusBadge = $hasStatus
            ? "\n                <x-aicl-status-badge :status=\"\${$snake}->status\" />"
            : '';

        $content = <<<BLADE
@props(['{$snake}'])

<div {{ \$attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800']) }}>
    <a href="{{ route('{$plural}.show', \${$snake}) }}" class="block">
        <div class="flex items-start justify-between">
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                    {{ \${$snake}->{$displayField} }}
                </h3>
                @if(\${$snake}->description ?? null)
                    <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ Str::limit(strip_tags(\${$snake}->description), 100) }}
                    </p>
                @endif
            </div>{$statusBadge}
        </div>

        <div class="mt-3 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
            <span>{{ \${$snake}->created_at->diffForHumans() }}</span>
        </div>
    </a>
</div>
BLADE;

        $path = "{$viewDir}/components/{$snake}-card.blade.php";
        file_put_contents($path, $content);

        return "resources/views/{$snake}/components/{$snake}-card.blade.php";
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    private function generateFiltersComponent(string $viewDir, string $name, string $snake): string
    {
        $plural = Str::plural($snake);
        $filterFields = $this->getFilterableFields();

        $filterInputs = '';
        foreach ($filterFields as $field) {
            if ($field->isEnum() || $field->name === 'status') {
                $filterInputs .= <<<BLADE

            <x-aicl-combobox
                :options="\${$field->name}Options"
                name="filter[{$field->name}]"
                placeholder="All {$field->label()}"
                :clearable="true"
            />
BLADE;
            } elseif ($field->type === 'boolean') {
                $filterInputs .= <<<BLADE

            <x-aicl-combobox
                :options="[
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ]"
                name="filter[{$field->name}]"
                placeholder="{$field->label()}"
                :clearable="true"
            />
BLADE;
            }
        }

        if ($filterInputs === '') {
            $filterInputs = "\n            {{-- Add filter inputs here --}}";
        }

        $content = <<<BLADE
@props([])

<form method="GET" action="{{ route('{$plural}.index') }}" class="mb-6 flex flex-wrap items-center gap-3">
    <div class="flex flex-wrap items-center gap-3">{$filterInputs}
    </div>

    <button type="submit"
            class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
        Filter
    </button>

    @if(request()->has('filter'))
        <a href="{{ route('{$plural}.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
            Clear
        </a>
    @endif
</form>
BLADE;

        $path = "{$viewDir}/components/{$snake}-filters.blade.php";
        file_put_contents($path, $content);

        return "resources/views/{$snake}/components/{$snake}-filters.blade.php";
    }

    private function generateViewController(string $name, string $snake, string $plural): string
    {
        $columns = $this->buildTableColumns();
        $columnsPhp = $this->buildColumnsPhpArray($columns);
        $metadataPhp = $this->buildMetadataPhpArray($snake);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\\{$name};
use Illuminate\Http\Request;
use Illuminate\View\View;

/** View controller for {$name}. */
class {$name}ViewController extends Controller
{
    public function index(Request \$request): View
    {
        \$query = {$name}::query()->latest();

        // Apply filters
        if (\$filters = \$request->input('filter')) {
            foreach (\$filters as \$field => \$value) {
                if (\$value !== null && \$value !== '') {
                    \$query->where(\$field, \$value);
                }
            }
        }

        \${$plural} = \$query->paginate(25);

        // Transform data for the data table component
        \$columns = {$columnsPhp};

        \$tableData = \${$plural}->map(fn ({$name} \$item) => (array) \$item->only(array_column(\$columns, 'key')))->values()->all();

        return view('{$snake}.index', compact('{$plural}', 'columns', 'tableData'));
    }

    public function show({$name} \${$snake}): View
    {
        {$metadataPhp}

        return view('{$snake}.show', compact('{$snake}', 'metadata'));
    }
}
PHP;

        $path = app_path("Http/Controllers/{$name}ViewController.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Http/Controllers/{$name}ViewController.php";
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    private function generateWebRoutes(string $name, string $snake, string $plural): string
    {
        $routesFile = base_path('routes/web.php');
        $routeEntry = <<<PHP


// {$name} public views
Route::controller(\\App\\Http\\Controllers\\{$name}ViewController::class)
    ->prefix('{$plural}')
    ->name('{$plural}.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{record}', 'show')->name('show');
    });
PHP;

        // Check if routes already exist
        $existing = file_exists($routesFile) ? (file_get_contents($routesFile) ?: '') : '';
        if (! str_contains($existing, "{$name}ViewController")) {
            file_put_contents($routesFile, $existing.$routeEntry);
        }

        return 'routes/web.php';
    }

    // ─── Helper Methods ──────────────────────────────────────────

    /**
     * @return array<int, array{key: string, label: string, sortable: bool}>
     */
    private function buildTableColumns(): array
    {
        $columns = [];

        if ($this->ctx->fields === null) {
            return [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
            ];
        }

        $columns[] = ['key' => 'id', 'label' => 'ID', 'sortable' => true];

        foreach ($this->ctx->fields as $field) {
            if ($field->isForeignId()) {
                continue; // Skip FK IDs in table display
            }
            $columns[] = [
                'key' => $field->name,
                'label' => $field->label(),
                'sortable' => in_array($field->type, ['string', 'integer', 'float', 'date', 'datetime', 'boolean', 'enum'], true),
            ];
        }

        $columns[] = ['key' => 'created_at', 'label' => 'Created', 'sortable' => true];

        return $columns;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function buildColumnsPhpArray(array $columns): string
    {
        $lines = ["[\n"];
        foreach ($columns as $col) {
            $sortable = $col['sortable'] ? 'true' : 'false';
            $lines[] = "            ['key' => '{$col['key']}', 'label' => '{$col['label']}', 'sortable' => {$sortable}],\n";
        }
        $lines[] = '        ]';

        return implode('', $lines);
    }

    private function buildMetadataItems(string $snake): string
    {
        if ($this->ctx->fields === null) {
            return "[\n                ['label' => 'Created', 'value' => \${$snake}->created_at->format('M j, Y')],\n            ]";
        }

        $items = [];
        foreach ($this->ctx->fields as $field) {
            if (in_array($field->name, ['description', 'body', 'content'], true)) {
                continue;
            }
            if ($field->isForeignId()) {
                continue;
            }
            $items[] = $this->buildMetadataItemForField($field, $snake);
        }

        $items[] = "['label' => 'Created', 'value' => \${$snake}->created_at->format('M j, Y')]";
        $items[] = "['label' => 'Updated', 'value' => \${$snake}->updated_at->format('M j, Y')]";

        return "[\n                ".implode(",\n                ", $items).",\n            ]";
    }

    private function buildMetadataPhpArray(string $snake): string
    {
        $items = $this->buildMetadataItems($snake);

        return "\$metadata = {$items};";
    }

    private function buildMetadataItemForField(FieldDefinition $field, string $snake): string
    {
        if ($field->type === 'datetime' || $field->type === 'date') {
            return "['label' => '{$field->label()}', 'value' => \${$snake}->{$field->name}?->format('M j, Y')]";
        }

        if ($field->type === 'boolean') {
            return "['label' => '{$field->label()}', 'value' => \${$snake}->{$field->name} ? 'Yes' : 'No']";
        }

        if ($field->isEnum()) {
            return "['label' => '{$field->label()}', 'value' => \${$snake}->{$field->name}?->value]";
        }

        return "['label' => '{$field->label()}', 'value' => \${$snake}->{$field->name}]";
    }

    private function hasStatusField(): bool
    {
        if ($this->ctx->hasStates()) {
            return true;
        }

        if ($this->ctx->fields === null) {
            return false;
        }

        foreach ($this->ctx->fields as $field) {
            if ($field->name === 'status') {
                return true;
            }
        }

        return false;
    }

    private function buildStatusFilterHtml(string $snake): string
    {
        return <<<BLADE
    {{-- Status Filter --}}
    @include('{$snake}.components.{$snake}-filters')

BLADE;
    }

    /**
     * @return array<int, FieldDefinition>
     */
    private function getFilterableFields(): array
    {
        if ($this->ctx->fields === null) {
            return [];
        }

        return array_filter(
            $this->ctx->fields,
            fn ($f) => $f->isEnum() || $f->type === 'boolean' || $f->name === 'status',
        );
    }
}
