<x-filament-panels::page>
    <div class="space-y-8">

        {{-- Modal --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Modal</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Dialog overlay with backdrop, focus trap, and size variants. Uses Alpine.js for open/close state.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Size Variants</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['sm', 'md', 'lg', 'xl', 'full'] as $size)
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
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Drawer --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Drawer</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Slide-over panel from left or right edge. Use for detail panels, filters, and forms.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex gap-2">
                    <div x-data="{ open: false }">
                        <button @click="open = true" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Open Right</button>
                        <x-aicl-drawer>
                            <x-slot:title>Right Drawer</x-slot:title>
                            <div class="space-y-4">
                                <p class="text-gray-600 dark:text-gray-400">This drawer slides in from the right. Perfect for detail panels.</p>
                                <x-aicl-metadata-list :items="['Status' => 'Active', 'Created' => 'Feb 16, 2026', 'Priority' => 'High']" />
                            </div>
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
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Dropdown --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Dropdown</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Floating menu anchored to a trigger element. Uses Floating UI for positioning.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex gap-4">
                    <x-aicl-dropdown>
                        <x-slot:trigger>
                            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Actions</button>
                        </x-slot:trigger>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Edit</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Duplicate</a>
                        <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">Delete</a>
                    </x-aicl-dropdown>

                    <x-aicl-dropdown align="bottom-end">
                        <x-slot:trigger>
                            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">Options ▾</button>
                        </x-slot:trigger>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Sort by Name</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Sort by Date</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Filter Active</a>
                    </x-aicl-dropdown>
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Accordion --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Accordion</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Collapsible content sections. Supports single or multiple open panels.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Single Expand (default)</h3>
                    <x-aicl-accordion :defaultOpen="['general']">
                        <x-aicl-accordion-item name="general" label="General Settings" icon="heroicon-o-cog-6-tooth">
                            <p>Configure general application settings like name, timezone, and language.</p>
                        </x-aicl-accordion-item>
                        <x-aicl-accordion-item name="notifications" label="Notifications" icon="heroicon-o-bell">
                            <p>Manage notification preferences for email, SMS, and push notifications.</p>
                        </x-aicl-accordion-item>
                        <x-aicl-accordion-item name="security" label="Security" icon="heroicon-o-shield-check">
                            <p>Two-factor authentication, session management, and API tokens.</p>
                        </x-aicl-accordion-item>
                    </x-aicl-accordion>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Allow Multiple</h3>
                    <x-aicl-accordion :allowMultiple="true" :defaultOpen="['faq-1', 'faq-2']">
                        <x-aicl-accordion-item name="faq-1" label="What is AICL?">
                            <p>AICL is an AI-first Laravel application framework for building dashboard applications.</p>
                        </x-aicl-accordion-item>
                        <x-aicl-accordion-item name="faq-2" label="How do components work?">
                            <p>Components use a Single Directory Component (SDC) architecture with co-located PHP, Blade, and JS.</p>
                        </x-aicl-accordion-item>
                        <x-aicl-accordion-item name="faq-3" label="Can I customize the theme?">
                            <p>Yes, use the design tokens in theme.css to customize colors, fonts, and spacing.</p>
                        </x-aicl-accordion-item>
                    </x-aicl-accordion>
                </div>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Tabs (Enhanced) --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tabs</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tabbed content switcher with keyboard navigation, URL persistence, and orientation options.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-tabs defaultTab="overview">
                    <x-aicl-tab-panel name="overview" label="Overview">
                        <p class="py-4 text-gray-600 dark:text-gray-400">Overview content with summary statistics and key information.</p>
                    </x-aicl-tab-panel>
                    <x-aicl-tab-panel name="details" label="Details">
                        <p class="py-4 text-gray-600 dark:text-gray-400">Detailed information with form fields and metadata.</p>
                    </x-aicl-tab-panel>
                    <x-aicl-tab-panel name="activity" label="Activity">
                        <p class="py-4 text-gray-600 dark:text-gray-400">Activity timeline showing recent changes and events.</p>
                    </x-aicl-tab-panel>
                </x-aicl-tabs>
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- Combobox --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Combobox</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Searchable select with typeahead filtering. Supports async search, multiple selection, and clearable values.</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Basic Usage</h3>
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
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- DataTable --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">DataTable</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Client-side data table with sorting, filtering, pagination, and row selection. For static/pre-loaded data outside Filament.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <x-aicl-data-table
                    :columns="[
                        ['key' => 'name', 'label' => 'Name'],
                        ['key' => 'email', 'label' => 'Email'],
                        ['key' => 'role', 'label' => 'Role'],
                        ['key' => 'status', 'label' => 'Status'],
                    ]"
                    :data="[
                        ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'Admin', 'status' => 'Active'],
                        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'Editor', 'status' => 'Active'],
                        ['name' => 'Carol White', 'email' => 'carol@example.com', 'role' => 'Viewer', 'status' => 'Inactive'],
                        ['name' => 'David Brown', 'email' => 'david@example.com', 'role' => 'Editor', 'status' => 'Active'],
                    ]"
                    :selectable="true"
                />
            </div>
        </div>

        <x-aicl-divider label="Next Component" />

        {{-- CommandPalette --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">CommandPalette</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Spotlight-style command palette. Opens with Ctrl+K / ⌘K. Supports grouped commands and keyboard navigation.</p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-600 dark:text-gray-400">Press <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:border-gray-600 dark:bg-gray-700">Ctrl+K</kbd> or <kbd class="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:border-gray-600 dark:bg-gray-700">⌘K</kbd> to open the command palette.</p>
                <x-aicl-command-palette />
            </div>
        </div>

    </div>
</x-filament-panels::page>
