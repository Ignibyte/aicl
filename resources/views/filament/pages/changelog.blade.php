<x-filament-panels::page>
    <div class="fi-changelog prose prose-sm dark:prose-invert max-w-none rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10
                prose-headings:font-semibold
                prose-h2:mt-8 prose-h2:border-b prose-h2:border-gray-200 prose-h2:pb-2 dark:prose-h2:border-white/10
                prose-h3:text-primary-600 dark:prose-h3:text-primary-400
                prose-h4:text-gray-700 dark:prose-h4:text-gray-300
                prose-code:rounded prose-code:bg-gray-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-xs prose-code:font-medium prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-white/10
                prose-hr:border-gray-200 dark:prose-hr:border-white/10
                prose-strong:text-gray-900 dark:prose-strong:text-white
                prose-a:text-primary-600 dark:prose-a:text-primary-400">
        {!! $this->getChangelogHtml() !!}
    </div>
</x-filament-panels::page>
