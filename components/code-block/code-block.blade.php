@props([
    'code',
    'language' => 'blade',
])

<div
    x-data="{
        shown: false,
        copied: false,
        code: @js($code),
        async copy() {
            await navigator.clipboard.writeText(this.code);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
    }"
    {{ $attributes->merge(['class' => 'mt-2']) }}
>
    <button
        type="button"
        @click="shown = !shown"
        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
    >
        <x-filament::icon
            icon="heroicon-m-code-bracket"
            class="h-4 w-4"
        />
        <span x-text="shown ? 'Hide Code' : 'Show Code'"></span>
    </button>

    <div
        x-show="shown"
        x-cloak
        x-collapse.duration.200ms
        class="relative mt-2 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="absolute right-2 top-2">
            <button
                type="button"
                @click="copy()"
                class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-gray-700"
            >
                <template x-if="!copied">
                    <span class="flex items-center gap-1">
                        <x-filament::icon icon="heroicon-m-clipboard-document" class="h-3.5 w-3.5" />
                        Copy
                    </span>
                </template>
                <template x-if="copied">
                    <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                        <x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5" />
                        Copied!
                    </span>
                </template>
            </button>
        </div>
        <pre class="overflow-x-auto p-4 text-sm leading-relaxed"><code class="font-mono text-gray-800 dark:text-gray-200">{{ $code }}</code></pre>
    </div>
</div>
