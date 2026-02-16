<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <div class="flex items-center gap-2">
            @if($icon)
                <x-filament::icon :icon="$icon" class="h-5 w-5 text-gray-400" />
            @endif
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $heading }}</h3>
        </div>
    </div>
    <div class="px-6 py-4">
        @if(!empty($items))
            <x-aicl-metadata-list :items="$items" />
        @endif
        {{ $slot }}
    </div>
</div>
