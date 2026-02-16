@props([
    'position' => 'top-right',
    'maxVisible' => 5,
])

<div
    x-data
    {{ $attributes->merge(['class' => "fixed z-50 flex flex-col gap-3 {$positionClasses()}"]) }}
    role="status"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.visible({{ $maxVisible }})" :key="toast.id">
        <div
            x-show="toast.show"
            x-transition:enter="motion-safe:duration-300 motion-safe:ease-out"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="motion-safe:duration-200 motion-safe:ease-in"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="relative w-80 rounded-xl border border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-800"
            :role="toast.type === 'error' ? 'alert' : 'status'"
        >
            <div class="flex items-start gap-3">
                {{-- Icon --}}
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-green-100 text-green-600 dark:bg-green-500/10 dark:text-green-400': toast.type === 'success',
                        'bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400': toast.type === 'info',
                        'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400': toast.type === 'warning',
                        'bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400': toast.type === 'error',
                    }"
                >
                    <template x-if="toast.type === 'success'">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    </template>
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="toast.title"></p>
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400" x-text="toast.message" x-show="toast.message"></p>
                </div>

                {{-- Close --}}
                <button
                    type="button"
                    @click="$store.toasts.remove(toast.id)"
                    class="shrink-0 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-gray-700"
                    aria-label="Close notification"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Progress bar --}}
            <div
                x-show="toast.duration > 0"
                class="absolute bottom-0 left-0 h-0.5 rounded-b-xl"
                :class="{
                    'bg-green-500': toast.type === 'success',
                    'bg-blue-500': toast.type === 'info',
                    'bg-yellow-500': toast.type === 'warning',
                    'bg-red-500': toast.type === 'error',
                }"
                :style="`animation: toast-progress ${toast.duration}ms linear forwards`"
            ></div>
        </div>
    </template>
</div>

<style>
    @keyframes toast-progress {
        from { width: 100%; }
        to { width: 0%; }
    }
</style>
