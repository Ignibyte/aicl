<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="flex items-center gap-3">
        <x-filament::icon :icon="$icon" class="h-5 w-5 text-gray-400" />
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</span>
    </div>

    <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $actual }}</span>
        <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $target }}</span>
    </div>

    <div class="mt-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">Progress</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ $percentage() }}%</span>
        </div>
        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="{{ $progressColor() }} h-full rounded-full transition-all" style="width: {{ $percentage() }}%"></div>
        </div>
    </div>
</div>
