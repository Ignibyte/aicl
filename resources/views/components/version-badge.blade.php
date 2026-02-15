{{--
    Version Badge — Topbar indicator showing current AICL framework version.
    Rendered via PanelsRenderHook::TOPBAR_END.
--}}
<div class="fi-topbar-version-badge flex items-center">
    <a
        href="{{ filament()->getUrl() }}/changelog"
        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-gray-400 ring-1 ring-inset ring-gray-500/20 transition hover:text-gray-300 hover:ring-gray-400/30 dark:text-gray-500 dark:ring-gray-600/30 dark:hover:text-gray-400"
        title="View changelog"
    >
        v{{ $version }}
    </a>
</div>
