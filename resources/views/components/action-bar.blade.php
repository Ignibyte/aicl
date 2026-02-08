<div {{ $attributes->merge(['class' => "flex items-center gap-2 {$alignClass()}"]) }}>
    {{ $slot }}
</div>
