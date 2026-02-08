<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {$colorClasses()}"]) }}>
    @if($icon)
        <x-filament::icon :icon="$icon" class="h-3.5 w-3.5" />
    @endif
    {{ $label }}
</span>
