<x-filament-panels::page>
    <div class="space-y-8">

        {{-- Modal --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Modal</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Dialog overlay with backdrop, focus trap, and size variants.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap gap-2">
                    @foreach (['sm', 'md', 'lg', 'xl'] as $size)
                        <div x-data="{ open: false }">
                            <button @click="open = true" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Open {{ $size }}</button>
                            <x-aicl-modal :size="$size">
                                <x-slot:title>{{ ucfirst($size) }} Modal</x-slot:title>
                                <p class="text-gray-600 dark:text-gray-400">This is a {{ $size }} modal. Press Escape or click outside to close.</p>
                                <x-slot:footer>
                                    <x-aicl-action-bar>
                                        <button @click="open = false" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300">Cancel</button>
                                        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Confirm</button>
                                    </x-aicl-action-bar>
                                </x-slot:footer>
                            </x-aicl-modal>
                        </div>
                    @endforeach
                </div>
            </div>

            @php
$modalCode = <<<'BLADE'
<div x-data="{ open: false }">
    <button @click="open = true">Open Modal</button>
    <x-aicl-modal size="md">
        <x-slot:title>Confirm Action</x-slot:title>
        <p>Are you sure?</p>
        <x-slot:footer>
            <button @click="open = false">Cancel</button>
            <button>Confirm</button>
        </x-slot:footer>
    </x-aicl-modal>
</div>
BLADE;
            @endphp
            <x-aicl-code-block :code="$modalCode" />
            <x-aicl-component-reference component="modal" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Drawer --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Drawer</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Slide-over panel from left or right edge. Use for detail panels and filters.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex gap-2">
                    <div x-data="{ open: false }">
                        <button @click="open = true" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Open Right</button>
                        <x-aicl-drawer>
                            <x-slot:title>Right Drawer</x-slot:title>
                            <p class="text-gray-600 dark:text-gray-400">Slides in from the right. Perfect for detail panels.</p>
                        </x-aicl-drawer>
                    </div>
                    <div x-data="{ open: false }">
                        <button @click="open = true" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300">Open Left</button>
                        <x-aicl-drawer position="left">
                            <x-slot:title>Left Drawer</x-slot:title>
                            <p class="text-gray-600 dark:text-gray-400">Navigation-style drawer from the left.</p>
                        </x-aicl-drawer>
                    </div>
                </div>
            </div>

            @php
$drawerCode = <<<'BLADE'
<div x-data="{ open: false }">
    <button @click="open = true">Open Drawer</button>
    <x-aicl-drawer position="right">
        <x-slot:title>Details</x-slot:title>
        <p>Drawer content here.</p>
    </x-aicl-drawer>
</div>
BLADE;
            @endphp
            <x-aicl-code-block :code="$drawerCode" />
            <x-aicl-component-reference component="drawer" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Dropdown --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Dropdown</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Floating menu anchored to a trigger element. Uses Floating UI for positioning.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-dropdown>
                    <x-slot:trigger>
                        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Actions</button>
                    </x-slot:trigger>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Edit</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Duplicate</a>
                    <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">Delete</a>
                </x-aicl-dropdown>
            </div>

            @php
$dropdownCode = <<<'BLADE'
<x-aicl-dropdown align="bottom-end">
    <x-slot:trigger>
        <button>Actions</button>
    </x-slot:trigger>
    <a href="/edit">Edit</a>
    <a href="/delete">Delete</a>
</x-aicl-dropdown>
BLADE;
            @endphp
            <x-aicl-code-block :code="$dropdownCode" />
            <x-aicl-component-reference component="dropdown" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Accordion --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Accordion</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Collapsible content sections. Supports single or multiple open panels.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-accordion :defaultOpen="['general']">
                    <x-aicl-accordion-item name="general" label="General Settings" icon="heroicon-o-cog-6-tooth">
                        <p>Configure general application settings.</p>
                    </x-aicl-accordion-item>
                    <x-aicl-accordion-item name="notifications" label="Notifications" icon="heroicon-o-bell">
                        <p>Manage notification preferences.</p>
                    </x-aicl-accordion-item>
                    <x-aicl-accordion-item name="security" label="Security" icon="heroicon-o-shield-check">
                        <p>Two-factor authentication and session management.</p>
                    </x-aicl-accordion-item>
                </x-aicl-accordion>
            </div>

            @php
$accordionCode = <<<'BLADE'
<x-aicl-accordion :allowMultiple="true" :defaultOpen="['general']">
    <x-aicl-accordion-item name="general" label="General" icon="heroicon-o-cog-6-tooth">
        Content here
    </x-aicl-accordion-item>
</x-aicl-accordion>
BLADE;
            @endphp
            <x-aicl-code-block :code="$accordionCode" />
            <x-aicl-component-reference component="accordion" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Combobox --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Combobox</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Searchable select with typeahead filtering.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="max-w-xs">
                    <x-aicl-combobox
                        name="category"
                        placeholder="Select a category..."
                        :options="[
                            ['value' => 'web', 'label' => 'Web Development'],
                            ['value' => 'mobile', 'label' => 'Mobile Apps'],
                            ['value' => 'design', 'label' => 'UI/UX Design'],
                            ['value' => 'devops', 'label' => 'DevOps'],
                        ]"
                    />
                </div>
            </div>

            @php
$comboboxCode = <<<'BLADE'
<x-aicl-combobox
    name="category"
    placeholder="Select..."
    :options="[
        ['value' => 'web', 'label' => 'Web Development'],
        ['value' => 'mobile', 'label' => 'Mobile Apps'],
    ]"
/>
BLADE;
            @endphp
            <x-aicl-code-block :code="$comboboxCode" />
            <x-aicl-component-reference component="combobox" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- DataTable --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">DataTable</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Client-side data table with sorting, filtering, pagination, and row selection.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-data-table
                    :columns="[
                        ['key' => 'name', 'label' => 'Name'],
                        ['key' => 'email', 'label' => 'Email'],
                        ['key' => 'role', 'label' => 'Role'],
                    ]"
                    :data="[
                        ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'Admin'],
                        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'Editor'],
                        ['name' => 'Carol White', 'email' => 'carol@example.com', 'role' => 'Viewer'],
                    ]"
                    :selectable="true"
                />
            </div>

            @php
$dataTableCode = <<<'BLADE'
<x-aicl-data-table
    :columns="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'email', 'label' => 'Email'],
    ]"
    :data="$users"
    :selectable="true"
/>
BLADE;
            @endphp
            <x-aicl-code-block :code="$dataTableCode" />
            <x-aicl-component-reference component="data-table" />
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- CommandPalette --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">CommandPalette</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Spotlight-style command palette. Opens with Ctrl+K / ⌘K.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-600 dark:text-gray-400">Press <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:border-gray-600 dark:bg-gray-700">Ctrl+K</kbd> or <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:border-gray-600 dark:bg-gray-700">⌘K</kbd> to open.</p>
                <x-aicl-command-palette />
            </div>

            <x-aicl-code-block code='<x-aicl-command-palette />' />
            <x-aicl-component-reference component="command-palette" />
        </div>
    </div>
</x-filament-panels::page>
