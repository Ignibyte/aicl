{{--
    Navigation Switcher — Toggle Button

    Rendered in the topbar via PanelsRenderHook::USER_MENU_BEFORE.
    Uses Alpine.js to toggle between sidebar and topbar navigation layouts.
    Preference is stored in localStorage.
--}}
<div
    x-data="navigationSwitcher()"
    class="fi-topbar-nav-switcher flex items-center"
>
    <button
        x-on:click="toggle()"
        type="button"
        class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5 h-9 w-9 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
        x-bind:title="mode === 'sidebar' ? 'Switch to top navigation' : 'Switch to sidebar navigation'"
    >
        {{-- Sidebar icon (shown when in topbar mode → click to switch to sidebar) --}}
        <x-heroicon-o-view-columns
            x-show="mode === 'topbar'"
            x-cloak
            class="h-5 w-5"
        />

        {{-- Top bar icon (shown when in sidebar mode → click to switch to topbar) --}}
        <x-heroicon-o-arrows-right-left
            x-show="mode === 'sidebar'"
            x-cloak
            class="h-5 w-5"
        />
    </button>
</div>
