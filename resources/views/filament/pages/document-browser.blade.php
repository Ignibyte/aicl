<x-filament-panels::page>
    <div class="flex flex-col gap-6 lg:flex-row">
        {{-- File List --}}
        <div class="w-full lg:w-64 shrink-0">
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Documents</h3>
                </div>

                <nav class="px-2 pb-3">
                    @php
                        $files = $this->getFiles();
                        $currentGroup = null;
                    @endphp

                    @forelse($files as $fileEntry)
                        @if($currentGroup !== $fileEntry['group'])
                            @php $currentGroup = $fileEntry['group']; @endphp
                            <div class="px-2 pt-3 pb-1">
                                <span class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    {{ $currentGroup }}
                                </span>
                            </div>
                        @endif

                        <button
                            wire:click="$set('file', '{{ $fileEntry['relative'] }}')"
                            @class([
                                'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition',
                                'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400' => $file === $fileEntry['relative'],
                                'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' => $file !== $fileEntry['relative'],
                            ])
                        >
                            <x-filament::icon
                                icon="heroicon-o-document-text"
                                class="h-4 w-4 shrink-0 opacity-50"
                            />
                            <span class="truncate">{{ Str::headline($fileEntry['name']) }}</span>
                        </button>
                    @empty
                        <p class="px-2 py-4 text-sm text-gray-500">No documents found.</p>
                    @endforelse
                </nav>
            </div>
        </div>

        {{-- Document Content --}}
        <div class="min-w-0 flex-1">
            @if($file)
                <div class="prose prose-sm dark:prose-invert max-w-none rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10
                            prose-headings:font-semibold
                            prose-h2:mt-8 prose-h2:border-b prose-h2:border-gray-200 prose-h2:pb-2 dark:prose-h2:border-white/10
                            prose-h3:text-primary-600 dark:prose-h3:text-primary-400
                            prose-code:rounded prose-code:bg-gray-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-xs prose-code:font-medium prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-white/10
                            prose-hr:border-gray-200 dark:prose-hr:border-white/10
                            prose-strong:text-gray-900 dark:prose-strong:text-white
                            prose-a:text-primary-600 dark:prose-a:text-primary-400">
                    {!! $this->getDocumentHtml() !!}
                </div>
            @else
                <div class="flex items-center justify-center rounded-xl bg-white p-12 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-center">
                        <x-filament::icon
                            icon="heroicon-o-book-open"
                            class="mx-auto h-12 w-12 text-gray-400"
                        />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">Select a document</h3>
                        <p class="mt-1 text-sm text-gray-500">Choose a document from the sidebar to view its contents.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
