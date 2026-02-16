<div {{ $attributes->merge(['class' => 'grid grid-cols-1 lg:grid-cols-12 gap-6']) }}>
    <div class="{{ $reverse ? $sidebarCols() : $mainCols() }}">
        {{ $reverse ? $sidebar : $main }}
    </div>
    <div class="{{ $reverse ? $mainCols() : $sidebarCols() }}">
        {{ $reverse ? $main : $sidebar }}
    </div>
</div>
