<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</span>

    <div class="mt-2 flex items-end justify-between">
        <div>
            <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
            @if($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        @if(!empty($data))
            <svg viewBox="0 0 100 30" class="h-10 w-24" preserveAspectRatio="none">
                <path d="{{ $sparklinePath() }}"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      class="{{ $sparklineClass() }}" />
            </svg>
        @endif
    </div>
</div>
