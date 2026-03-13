<div class="flex items-center" x-data="{
    query: '',
    submit() {
        if (this.query.trim().length >= 2) {
            window.location.href = '{{ \Aicl\Filament\Pages\Search::getUrl() }}?q=' + encodeURIComponent(this.query.trim());
        }
    }
}" @keydown.meta.k.window.prevent="$refs.searchInput.focus()" @keydown.ctrl.k.window.prevent="$refs.searchInput.focus()">
    <form @submit.prevent="submit()" role="search" aria-label="Global search" class="relative">
        <div class="flex items-center gap-2">
            <div class="relative">
                <x-filament::icon
                    icon="heroicon-m-magnifying-glass"
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                />
                <input
                    x-ref="searchInput"
                    x-model="query"
                    type="search"
                    placeholder="Search..."
                    aria-label="Search across all content"
                    class="h-8 w-40 rounded-lg border border-gray-300 bg-white pl-8 pr-12 text-sm text-gray-950 placeholder-gray-400 transition focus:w-64 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-gray-500 dark:focus:border-primary-500"
                />
                <kbd class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 hidden items-center gap-0.5 rounded border border-gray-200 bg-gray-50 px-1 py-0.5 text-[10px] font-medium text-gray-400 sm:inline-flex dark:border-white/10 dark:bg-white/5 dark:text-gray-500" aria-hidden="true">
                    <span class="text-xs">{{ PHP_OS_FAMILY === 'Darwin' ? '⌘' : 'Ctrl' }}</span>K
                </kbd>
            </div>
        </div>
    </form>
</div>
