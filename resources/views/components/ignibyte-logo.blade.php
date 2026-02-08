<div {{ $attributes->merge(['class' => 'flex items-center gap-3 select-none']) }}>
    <img
        src="{{ $logoUrl() }}"
        alt="{{ $brandName() }} Logo"
        class="{{ $logoHeight() }} w-auto object-contain drop-shadow-[0_0_15px_rgba(249,115,22,0.5)]"
    />
    @unless($iconOnly)
        <span class="{{ $textSize() }} font-display font-bold tracking-wider bg-gradient-to-r from-gray-800 to-gray-600 dark:from-white dark:to-white/80 bg-clip-text text-transparent">
            {{ $brandName() }}
        </span>
    @endunless
</div>
