<x-filament-panels::page>
    <div class="space-y-8">

        {{-- Toast --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Toast</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Non-blocking notification toasts. Uses Alpine.js store for global state. Auto-dismisses with progress bar.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="$store.toasts.add({ type: 'success', title: 'Saved!', message: 'Your changes have been saved successfully.', duration: 5000 })"
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    >Success Toast</button>
                    <button
                        @click="$store.toasts.add({ type: 'info', title: 'Info', message: 'Here is some helpful information.', duration: 5000 })"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >Info Toast</button>
                    <button
                        @click="$store.toasts.add({ type: 'warning', title: 'Warning', message: 'Please review before continuing.', duration: 5000 })"
                        class="rounded-lg bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700"
                    >Warning Toast</button>
                    <button
                        @click="$store.toasts.add({ type: 'error', title: 'Error', message: 'Something went wrong.', duration: 5000 })"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                    >Error Toast</button>
                </div>
            </div>

            <x-aicl-toast />

            @php
$toastCode = <<<'BLADE'
{{-- Place once in your layout --}}
<x-aicl-toast />

{{-- Trigger from anywhere via Alpine.js --}}
<button @click="$store.toasts.add({
    type: 'success',
    title: 'Saved!',
    message: 'Changes saved.',
    duration: 5000,
})">Save</button>
BLADE;
            @endphp
            <x-aicl-code-block :code="$toastCode" />
            <x-aicl-component-reference component="toast" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Tooltip --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tooltip</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Floating informational tooltip. Appears on hover with configurable position and delay.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-6">
                    @foreach (['top', 'bottom', 'left', 'right'] as $pos)
                        <x-aicl-tooltip :content="'Tooltip on ' . $pos" :position="$pos">
                            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ ucfirst($pos) }}</button>
                        </x-aicl-tooltip>
                    @endforeach
                </div>
            </div>

            @php
$tooltipCode = <<<'BLADE'
<x-aicl-tooltip content="Helpful info" position="top">
    <button>Hover me</button>
</x-aicl-tooltip>
BLADE;
            @endphp
            <x-aicl-code-block :code="$tooltipCode" />
            <x-aicl-component-reference component="tooltip" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Badge --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Badge</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Inline label for categorization. Supports colors, variants, sizes, dots, icons, and removable state.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Colors (Soft)</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['gray', 'primary', 'blue', 'green', 'yellow', 'red'] as $color)
                            <x-aicl-badge :label="ucfirst($color)" :color="$color" />
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Solid + Features</h3>
                    <div class="flex flex-wrap gap-2">
                        <x-aicl-badge label="Solid" color="primary" variant="solid" />
                        <x-aicl-badge label="Active" color="green" :dot="true" />
                        <x-aicl-badge label="Warning" color="yellow" icon="heroicon-m-exclamation-triangle" />
                        <x-aicl-badge label="Removable" color="blue" :removable="true" />
                    </div>
                </div>
            </div>

            @php
$badgeCode = <<<'BLADE'
<x-aicl-badge label="Active" color="green" :dot="true" />
<x-aicl-badge label="Solid" color="primary" variant="solid" />
<x-aicl-badge label="Removable" color="blue" :removable="true" />
BLADE;
            @endphp
            <x-aicl-code-block :code="$badgeCode" />
            <x-aicl-component-reference component="badge" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Avatar --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Avatar</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">User avatar with image, initials fallback, and optional status indicator.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Sizes</h3>
                    <div class="flex items-end gap-3">
                        @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                            <x-aicl-avatar name="John Doe" :size="$size" />
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Status Indicators</h3>
                    <div class="flex items-center gap-3">
                        @foreach (['online', 'busy', 'away', 'offline'] as $status)
                            <x-aicl-avatar name="{{ ucfirst($status) }} User" :status="$status" />
                        @endforeach
                    </div>
                </div>
            </div>

            @php
$avatarCode = <<<'BLADE'
<x-aicl-avatar name="John Doe" size="md" status="online" />
BLADE;
            @endphp
            <x-aicl-code-block :code="$avatarCode" />
            <x-aicl-component-reference component="avatar" />
        </div>
    </div>
</x-filament-panels::page>
