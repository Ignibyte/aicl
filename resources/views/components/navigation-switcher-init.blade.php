{{--
    Navigation Switcher — Early Init Script

    Injected into <head> via PanelsRenderHook::HEAD_END to read the user's
    navigation preference from localStorage and apply it BEFORE first paint.
    This prevents flash-of-wrong-layout (FOWL).

    Only rendered when config('aicl.theme.navigation_layout') === 'switchable'.
--}}
<script>
    (function() {
        var mode = localStorage.getItem('aicl_nav_layout') || 'sidebar';
        document.documentElement.setAttribute('data-nav-mode', mode);
    })();
</script>
