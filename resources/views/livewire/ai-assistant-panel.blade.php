<div
    x-data="aiAssistantPanel({
        csrfToken: '{{ csrf_token() }}',
        reverbHost: '{{ config('aicl.ai.streaming.reverb.host') }}',
        reverbPort: {{ (int) config('aicl.ai.streaming.reverb.port') }},
        reverbScheme: '{{ config('aicl.ai.streaming.reverb.scheme') }}',
        reverbKey: '{{ config('broadcasting.connections.reverb.key', '') }}',
        authUrl: '/broadcasting/auth',
        keyboardShortcut: '{{ config('aicl.ai.assistant.keyboard_shortcut', 'cmd+j') }}',
    })"
    x-on:keydown.window="handleGlobalKeydown($event)"
    class="ai-assistant-widget"
    wire:ignore.self
>
    {{-- Floating Toggle Button --}}
    <button
        x-show="!panelOpen"
        x-on:click="togglePanel"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="scale-75 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        class="group fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary-500 text-white shadow-2xl shadow-primary-500/20 transition-all duration-300 hover:scale-105 hover:bg-primary-500/90 focus:outline-none focus:ring-4 focus:ring-primary-500/30 active:scale-95"
        title="AI Assistant ({{ config('aicl.ai.assistant.keyboard_shortcut', 'Cmd+J') }})"
    >
        {{-- Bot icon --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
        </svg>
    </button>

    {{-- Panel / Full-Screen Overlay --}}
    <div
        x-show="panelOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        :class="fullScreen
            ? 'fixed inset-0 z-50 flex'
            : 'fixed bottom-6 right-6 z-50 flex'"
        :style="!fullScreen ? 'width: 380px; height: 600px; max-width: calc(100vw - 3rem); max-height: calc(100vh - 3rem);' : ''"
        x-cloak
    >
        {{-- Backdrop (full-screen only) --}}
        <div
            x-show="fullScreen"
            x-on:click="toggleFullScreen"
            class="fixed inset-0 bg-black/60 backdrop-blur-md"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        {{-- Main Panel Container --}}
        <div
            :class="fullScreen
                ? 'relative z-10 m-4 flex min-w-0 flex-1 overflow-hidden rounded-2xl border border-white/5 bg-gray-900 shadow-2xl sm:m-6 md:m-8 lg:m-10'
                : 'flex min-w-0 flex-1 overflow-hidden rounded-xl border border-white/5 bg-gray-900 shadow-2xl'"
        >
            {{-- Conversation Sidebar (full-screen only) --}}
            <div
                x-show="fullScreen && sidebarOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                class="flex w-64 shrink-0 flex-col border-r border-white/5 bg-gray-950/80"
            >
                {{-- Sidebar Header with New Chat Button --}}
                <div class="px-4 pb-3 pt-4">
                    <button
                        x-on:click="newConversation"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Chat
                    </button>
                </div>

                {{-- Conversation List --}}
                <div class="flex-1 overflow-y-auto px-3 pb-3">
                    <div class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Recent</div>
                    @foreach ($this->conversations as $convo)
                        <div
                            wire:key="convo-{{ $convo->id }}"
                            x-data="{ hovered: false }"
                            x-on:mouseenter="hovered = true"
                            x-on:mouseleave="hovered = false"
                            x-on:click="switchConversation('{{ $convo->id }}')"
                            :class="activeConversationId === '{{ $convo->id }}'
                                ? 'bg-primary-500/15 text-primary-400'
                                : 'text-gray-300 hover:bg-white/5'"
                            class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                            </svg>
                            <span class="flex-1 truncate">{{ $convo->display_title }}</span>
                            <button
                                x-show="hovered"
                                x-on:click.stop="deleteConversation('{{ $convo->id }}')"
                                class="shrink-0 rounded p-1 text-gray-500 transition hover:text-danger-400"
                                title="Delete"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    @endforeach

                    @if ($this->conversations->isEmpty())
                        <div class="flex flex-col items-center justify-center px-3 py-8 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                            </svg>
                            <p class="mt-2 text-xs text-gray-500">No conversations yet</p>
                            <p class="mt-0.5 text-xs text-gray-600">Start chatting to create one</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Chat Area --}}
            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Header --}}
                <div class="flex items-center border-b border-white/5 px-3 py-2">
                    {{-- Left side: Sidebar toggle OR AI Assistant label --}}
                    <div class="flex items-center gap-2">
                        {{-- Sidebar Toggle (full-screen only) --}}
                        <button
                            x-show="fullScreen"
                            x-on:click="sidebarOpen = !sidebarOpen"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-white/10 hover:text-white"
                            title="Toggle sidebar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        {{-- AI Assistant title with icon --}}
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-500/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold leading-tight text-white">AI Assistant</div>
                                <div class="text-[10px] leading-tight text-gray-400">Ready to help</div>
                            </div>
                        </div>
                    </div>

                    {{-- Spacer --}}
                    <div class="flex-1"></div>

                    {{-- Right side: Agent selector + action buttons --}}
                    <div class="flex min-w-0 items-center gap-1">
                        {{-- Agent Selector (compact dropdown, Replit-style) --}}
                        <div class="relative inline-flex" :class="fullScreen ? 'w-[170px]' : 'w-[120px]'">
                            <select
                                x-model="selectedAgentId"
                                x-on:change="onAgentChange"
                                class="h-9 w-full cursor-pointer rounded-lg border-0 bg-transparent py-1 pl-3 pr-6 text-xs font-medium text-gray-300 ring-1 ring-inset ring-white/5 transition hover:ring-white/15 focus:ring-2 focus:ring-primary-500"
                                style="-webkit-appearance: none; -moz-appearance: none; appearance: none;"
                            >
                                @foreach ($this->agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        {{-- New Chat (full-screen only) --}}
                        <button
                            x-show="fullScreen"
                            x-on:click="newConversation"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-white/10 hover:text-white"
                            title="New conversation"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </button>

                        {{-- Expand / Collapse --}}
                        <button
                            x-on:click="toggleFullScreen"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-white/10 hover:text-white"
                            :title="fullScreen ? 'Collapse' : 'Expand'"
                        >
                            <svg x-show="!fullScreen" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                            <svg x-show="fullScreen" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                            </svg>
                        </button>

                        {{-- Close --}}
                        <button
                            x-on:click="closePanel"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-white/10 hover:text-white"
                            title="Close (Esc)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Messages --}}
                <div
                    x-ref="chatContainer"
                    class="flex-1 overflow-y-auto"
                    :class="fullScreen ? 'px-6 py-6' : 'px-4 py-4'"
                >
                    {{-- Empty State --}}
                    <template x-if="messages.length === 0 && !loading">
                        <div class="flex h-full items-center justify-center">
                            <div class="max-w-sm text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                                    </svg>
                                </div>
                                <h4 class="mt-4 text-base font-semibold text-white">How can I help?</h4>
                                <p class="mt-1 text-sm text-gray-400">Ask me anything to get started.</p>
                            </div>
                        </div>
                    </template>

                    {{-- Message List --}}
                    <div class="space-y-6">
                        <template x-for="(msg, index) in messages" :key="index">
                            <div>
                                {{-- User Message --}}
                                <template x-if="msg.role === 'user'">
                                    <div>
                                        {{-- Name + time + avatar row (right-aligned) --}}
                                        <div class="mb-1.5 flex items-center justify-end gap-2">
                                            <span class="text-xs font-semibold text-white">You</span>
                                            <span class="text-[11px] text-gray-500" x-text="msg.timestamp || ''"></span>
                                            @if (filament()->auth()->user()?->getFilamentAvatarUrl())
                                                <img src="{{ filament()->auth()->user()->getFilamentAvatarUrl() }}" alt="You" class="h-7 w-7 rounded-full object-cover" />
                                            @else
                                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-600 text-xs font-semibold text-white">
                                                    {{ strtoupper(substr(filament()->auth()->user()?->name ?? 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        {{-- Bubble --}}
                                        <div class="ml-auto w-fit max-w-[80%]">
                                            <div class="rounded-2xl rounded-tr-sm bg-primary-500 px-4 py-2.5 text-white">
                                                <div x-html="msg.content ? msg.content.replace(/\n/g, '<br>') : ''" class="whitespace-pre-wrap break-words text-sm leading-relaxed"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Assistant Message --}}
                                <template x-if="msg.role !== 'user'">
                                    <div>
                                        {{-- Avatar + name + time row (left-aligned) --}}
                                        <div class="mb-1.5 flex items-center gap-2">
                                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-500/20">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-semibold text-white" x-text="msg.agent_name || 'Assistant'"></span>
                                            <span class="text-[11px] text-gray-500" x-text="msg.timestamp || ''"></span>
                                        </div>

                                        {{-- Tool Call Chips --}}
                                        <template x-if="msg.tools && msg.tools.length > 0">
                                            <div class="mb-1.5 ml-9 flex flex-wrap gap-1">
                                                <template x-for="(tool, tIdx) in msg.tools" :key="tIdx">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2 py-0.5 text-xs font-medium text-primary-400 ring-1 ring-inset ring-primary-400/20">
                                                        <svg class="h-2.5 w-2.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M14.5 10a4.5 4.5 0 004.284-5.882c-.105-.324-.51-.391-.752-.15L15.34 6.66a.454.454 0 01-.493.11 3.01 3.01 0 01-1.618-1.616.455.455 0 01.11-.494l2.694-2.692c.24-.241.174-.647-.15-.752a4.5 4.5 0 00-5.873 4.575c.055.873-.128 1.808-.8 2.368l-7.23 6.024a2.724 2.724 0 103.837 3.837l6.024-7.23c.56-.672 1.495-.855 2.368-.8.18.013.36.017.54.017zM5 16a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span x-text="tool.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Bubble --}}
                                        <div x-show="msg.content" class="max-w-full">
                                            <div class="rounded-2xl rounded-tl-sm border border-white/5 bg-gray-800/50 px-4 py-2.5 text-gray-100">
                                                <div x-html="msg.content ? msg.content.replace(/\n/g, '<br>') : ''" class="whitespace-pre-wrap break-words text-sm leading-relaxed [overflow-wrap:anywhere]"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Loading Indicator --}}
                    <template x-if="loading && !_currentResponse">
                        <div class="mt-4 flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-500/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                                </svg>
                            </div>
                            <div class="rounded-2xl rounded-tl-sm border border-white/5 bg-gray-800/50 px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 0ms"></span>
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 150ms"></span>
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 300ms"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Error Banner --}}
                <template x-if="error">
                    <div class="mx-4 mb-2 rounded-xl bg-danger-950/50 px-3.5 py-2.5 text-xs text-danger-400">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <span x-text="error" class="flex-1"></span>
                            <button x-on:click="error = null" class="rounded p-0.5 text-danger-400 transition hover:bg-danger-500/20 hover:text-danger-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Input Bar --}}
                <div class="border-t border-white/5" :class="fullScreen ? 'px-6 py-4' : 'px-3 py-3'">
                    <div class="flex items-end gap-2 rounded-xl border border-white/5 bg-gray-800/50 p-2 transition-colors focus-within:border-primary-500/50 focus-within:ring-1 focus-within:ring-primary-500/50">
                        {{-- Attachment button (Replit-style) --}}
                        <button class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:text-gray-300" title="Attach file">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                        </button>

                        <textarea
                            x-model="prompt"
                            x-on:keydown="handleKeydown"
                            :disabled="loading"
                            x-ref="promptInput"
                            rows="1"
                            placeholder="Type a message..."
                            class="block w-full resize-none border-0 bg-transparent px-1 py-1.5 text-sm text-white placeholder:text-gray-500 focus:ring-0 disabled:opacity-50"
                            style="min-height: 2rem; max-height: 8rem; overflow-y: auto;"
                            x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px';"
                        ></textarea>

                        <button
                            x-on:click="send"
                            :disabled="loading || !prompt.trim()"
                            :class="prompt.trim() ? 'bg-primary-500 text-white hover:bg-primary-400' : 'text-gray-500'"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-all disabled:opacity-40"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
