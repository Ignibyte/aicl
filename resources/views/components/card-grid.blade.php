<div {{ $attributes->merge(['class' => "grid {$gridCols()} gap-{$gap}"]) }}>
    {{ $slot }}
</div>
