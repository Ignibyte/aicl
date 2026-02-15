<x-filament-panels::page>
    <div
        x-data="aiChat({
            askUrl: '/ai/ask',
            csrfToken: '{{ csrf_token() }}',
            reverbHost: '{{ config('aicl.ai.streaming.reverb.host') }}',
            reverbPort: {{ (int) config('aicl.ai.streaming.reverb.port') }},
            reverbScheme: '{{ config('aicl.ai.streaming.reverb.scheme') }}',
            reverbKey: '{{ config('broadcasting.connections.reverb.key', '') }}',
            authUrl: '/broadcasting/auth',
        })"
        class="flex flex-col h-[calc(100vh-12rem)]"
    >
        {{-- Chat Messages --}}
        <div
            x-ref="chatContainer"
            class="flex-1 overflow-y-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            {{-- Empty State --}}
            <template x-if="messages.length === 0">
                <div class="flex h-full items-center justify-center">
                    <div class="text-center">
                        <x-filament::icon
                            icon="heroicon-o-sparkles"
                            class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                        />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">AI Assistant</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ask a question to get started.</p>
                    </div>
                </div>
            </template>

            {{-- Messages --}}
            <div class="space-y-4">
                <template x-for="(msg, index) in messages" :key="index">
                    <div x-show="msg.content || (msg.tools && msg.tools.length)" :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="max-w-[80%]">
                            {{-- Tool Call Chips — shown above the assistant bubble --}}
                            <template x-if="msg.tools && msg.tools.length > 0">
                                <div class="mb-1.5 flex flex-wrap gap-1.5">
                                    <template x-for="(tool, tIdx) in msg.tools" :key="tIdx">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/20">
                                            <svg class="h-3 w-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M14.5 10a4.5 4.5 0 004.284-5.882c-.105-.324-.51-.391-.752-.15L15.34 6.66a.454.454 0 01-.493.11 3.01 3.01 0 01-1.618-1.616.455.455 0 01.11-.494l2.694-2.692c.24-.241.174-.647-.15-.752a4.5 4.5 0 00-5.873 4.575c.055.873-.128 1.808-.8 2.368l-7.23 6.024a2.724 2.724 0 103.837 3.837l6.024-7.23c.56-.672 1.495-.855 2.368-.8.18.013.36.017.54.017zM5 16a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span x-text="tool.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                        </span>
                                    </template>
                                </div>
                            </template>

                            {{-- Message Bubble --}}
                            <div
                                x-show="msg.content"
                                :class="msg.role === 'user'
                                    ? 'rounded-2xl rounded-br-md bg-primary-600 px-4 py-2.5 text-white'
                                    : 'rounded-2xl rounded-bl-md bg-gray-100 px-4 py-2.5 text-gray-900 dark:bg-gray-800 dark:text-gray-100'"
                            >
                                <div x-html="msg.content ? msg.content.replace(/\n/g, '<br>') : ''" class="whitespace-pre-wrap text-sm leading-relaxed"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Loading Indicator (shown while waiting for first token) --}}
            <template x-if="loading && !_currentResponse">
                <div class="flex justify-start mt-4">
                    <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-gray-100 px-4 py-2.5 dark:bg-gray-800">
                        <div class="flex items-center gap-1 py-1">
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Error Banner --}}
        <template x-if="error">
            <div class="mt-2 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-400">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 shrink-0" />
                    <span x-text="error"></span>
                    <button x-on:click="error = null" class="ml-auto text-danger-500 hover:text-danger-700">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </template>

        {{-- Input Bar --}}
        <div class="mt-3 flex gap-2">
            <textarea
                x-model="prompt"
                x-on:keydown="handleKeydown"
                :disabled="loading"
                rows="1"
                placeholder="Ask a question..."
                class="fi-input block w-full rounded-lg border-none bg-white py-2.5 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:opacity-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500"
                style="resize: none; min-height: 2.5rem; max-height: 8rem; overflow-y: auto;"
                x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px';"
            ></textarea>

            <x-filament::icon-button
                icon="heroicon-o-paper-airplane"
                x-on:click="send"
                x-bind:disabled="loading || !prompt.trim()"
                color="primary"
                size="lg"
                class="shrink-0 self-end"
            />
        </div>
    </div>
</x-filament-panels::page>
