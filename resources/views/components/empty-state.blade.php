<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <div class="mb-4 rounded-full bg-gray-100 p-4 dark:bg-gray-800">
        <x-filament::icon :icon="$icon" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
    </div>

    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
        {{ $heading }}
    </h3>

    @if($description)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $description }}
        </p>
    @endif

    @if($actionUrl && $actionLabel)
        <div class="mt-6">
            <a href="{{ $actionUrl }}"
               class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                {{ $actionLabel }}
            </a>
        </div>
    @endif
</div>
