<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-lg border p-4 {$typeClasses()}"]) }}
     x-data="{ show: true }"
     x-show="show"
     x-transition>
    <x-filament::icon :icon="$defaultIcon()" class="mt-0.5 h-5 w-5 shrink-0" />

    <div class="flex-1 text-sm">
        {{ $slot }}
    </div>

    @if($dismissible)
        <button type="button" @click="show = false" class="shrink-0 rounded p-1 hover:opacity-75">
            <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
            <span class="sr-only">Dismiss</span>
        </button>
    @endif
</div>
