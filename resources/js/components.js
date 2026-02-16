/**
 * AICL SDC Component JS Entry Point
 *
 * Auto-imports all *.js files from co-located component directories.
 * Each component's JS module registers its own Alpine data/store.
 *
 * This file is imported by the Filament asset system via aicl-widgets.js.
 */
const modules = import.meta.glob('../../components/*/*.js', { eager: true });

// Log discovered modules in dev mode
if (import.meta.env.DEV && Object.keys(modules).length > 0) {
    console.log(`[AICL] Loaded ${Object.keys(modules).length} component JS modules`);
}
