<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="flex items-center gap-3">
        <div class="rounded-lg {{ $iconBgClass() }} p-2">
            <x-filament::icon :icon="$icon" @class(['h-5 w-5', $iconTextClass()]) />
        </div>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</span>
    </div>

    <div class="mt-3">
        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
    </div>

    @if($description || ($trend && $trendValue))
        <div class="mt-2 flex items-center gap-2">
            @if($trend && $trendValue)
                <span class="inline-flex items-center gap-1 text-sm font-medium {{ $trendColor() }}">
                    @if($trendIcon())
                        <x-filament::icon :icon="$trendIcon()" class="h-4 w-4" />
                    @endif
                    {{ $trendValue }}
                </span>
            @endif
            @if($description)
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</span>
            @endif
        </div>
    @endif
</div>
