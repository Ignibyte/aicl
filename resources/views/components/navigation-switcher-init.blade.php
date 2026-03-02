{{--
    Navigation Switcher — Early Init Script

    Injected into <head> via PanelsRenderHook::HEAD_END to read the user's
    navigation preference from localStorage and apply it BEFORE first paint.
    This prevents flash-of-wrong-layout (FOWL).

    Also reads sidebar collapse preference (aicl_sidebar_collapsed) and sets
    data-sidebar-collapsed on <html> so CSS can hide the expanded sidebar
    before Alpine boots, preventing flash-of-wrong-sidebar-width.

    Always rendered — the toggle is always available.
--}}
<script>
    (function() {
        var mode = localStorage.getItem('aicl_nav_layout') || 'sidebar';
        document.documentElement.setAttribute('data-nav-mode', mode);

        // Persist sidebar collapse state — only relevant in sidebar mode on desktop
        if (mode === 'sidebar' && window.innerWidth >= 1024) {
            var collapsed = localStorage.getItem('aicl_sidebar_collapsed');
            if (collapsed === 'true') {
                document.documentElement.setAttribute('data-sidebar-collapsed', 'true');
            }
        }
    })();
</script>
