@if($label)
    <div {{ $attributes->merge(['class' => 'relative my-6']) }}>
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
        </div>
        <div class="relative flex justify-center">
            <span class="bg-white px-3 text-sm text-gray-500 dark:bg-gray-900 dark:text-gray-400">{{ $label }}</span>
        </div>
    </div>
@else
    <hr {{ $attributes->merge(['class' => 'my-6 border-t border-gray-200 dark:border-gray-700']) }} />
@endif
