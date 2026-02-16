@props([
    'src' => null,
    'alt' => '',
    'name' => null,
    'size' => 'md',
    'rounded' => 'full',
    'status' => null,
])

<div {{ $attributes->merge(['class' => "relative inline-flex shrink-0 {$sizeClasses()}"]) }}>
    @if($src)
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            class="{{ $sizeClasses() }} {{ $roundedClass() }} object-cover"
            loading="lazy"
        />
    @else
        <span
            class="{{ $sizeClasses() }} {{ $roundedClass() }} inline-flex items-center justify-center bg-primary-100 font-medium text-primary-700 dark:bg-primary-500/20 dark:text-primary-400"
            @if($name) aria-label="{{ $name }}" @endif
        >
            {{ $initials() }}
        </span>
    @endif

    @if($status)
        <span class="absolute bottom-0 right-0 {{ $statusDotSize() }} {{ $statusColor() }} {{ $roundedClass() }} ring-2 ring-white dark:ring-gray-800">
            <span class="sr-only">{{ ucfirst($status) }}</span>
        </span>
    @endif
</div>
