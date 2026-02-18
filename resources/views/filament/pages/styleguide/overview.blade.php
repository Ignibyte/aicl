<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Header --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">AICL Component Library</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                A curated set of Blade components designed for AI-generated dashboard interfaces.
                Each component includes AI decision rules that guide code generation.
            </p>
        </div>

        {{-- Stats Row --}}
        <x-aicl-stats-row>
            <x-aicl-stat-card label="Total Components" :value="(string) $totalComponents" icon="heroicon-o-squares-2x2" />
            <x-aicl-stat-card label="Categories" :value="(string) $totalCategories" icon="heroicon-o-tag" />
            <x-aicl-stat-card label="With JS Module" :value="(string) $jsModuleCount" icon="heroicon-o-code-bracket" />
        </x-aicl-stats-row>

        {{-- Category Cards --}}
        <x-aicl-card-grid cols="2">
            @foreach ($categories as $category)
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-{{ $category['color'] }}-100 text-{{ $category['color'] }}-600 dark:bg-{{ $category['color'] }}-900/30 dark:text-{{ $category['color'] }}-400">
                                <x-filament::icon :icon="$category['icon']" class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $category['label'] }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($category['components']) }} {{ str('component')->plural(count($category['components'])) }}</p>
                            </div>
                        </div>
                        @if($category['slug'])
                            <a href="{{ url('/admin/' . $category['slug']) }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                View &rarr;
                            </a>
                        @endif
                    </div>
                    <ul class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($category['components'] as $component)
                            <li>
                                <span class="font-medium text-gray-900 dark:text-gray-200">{{ $component->name }}</span>
                                <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
                                {{ $component->description }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </x-aicl-card-grid>
    </div>
</x-filament-panels::page>
