@php
    $tag = $href ? 'a' : 'button';
@endphp
<{{ $tag }}
    {{ $attributes->merge([
        'class' => 'inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white',
        'title' => $label,
    ]) }}
    @if($href) href="{{ $href }}" @endif
    @if(!$href) type="button" @endif
>
    <x-filament::icon :icon="$icon" class="h-5 w-5" />
    <span class="sr-only">{{ $label }}</span>
</{{ $tag }}>
