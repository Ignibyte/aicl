<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</span>

    <div class="mt-2">
        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
    </div>

    @if($description)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif

    <div class="mt-3">
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="{{ $progressBarClass() }} h-full rounded-full transition-all" style="width: {{ $progressWidth() }}%"></div>
        </div>
    </div>
</div>
