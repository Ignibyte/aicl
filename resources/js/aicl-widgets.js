/**
 * AICL Widget Alpine.js Components
 *
 * Registered via FilamentAsset in AiclServiceProvider.
 * These functions are referenced by x-data attributes in widget Blade views.
 */


window.pollingWidget = function ({ interval, pauseWhenHidden }) {
    return {
        _timer: null,
        paused: false,

        init() {
            this.startPolling();

            if (pauseWhenHidden) {
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.stopPolling();
                        this.paused = true;
                    } else {
                        this.paused = false;
                        this.$wire.poll();
                        this.startPolling();
                    }
                });
            }
        },

        startPolling() {
            this.stopPolling();
            this._timer = setInterval(() => this.$wire.poll(), interval * 1000);
        },

        stopPolling() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        destroy() {
            this.stopPolling();
        },
    };
};

/**
 * AI Chat — Minimal Pusher-protocol WebSocket client for AI streaming.
 *
 * Connects to Reverb via native WebSocket API (no Echo/Pusher dependency).
 * Subscribes to a private channel, receives streamed tokens, and renders
 * the response character-by-character.
 *
 * @param {Object} config
 * @param {string} config.askUrl - POST endpoint for AI requests
 * @param {string} config.csrfToken - CSRF token
 * @param {string} config.reverbHost - Reverb host (browser-accessible)
 * @param {number} config.reverbPort - Reverb port
 * @param {string} config.reverbScheme - 'http' or 'https'
 * @param {string} config.reverbKey - Reverb app key
 * @param {string} config.authUrl - Broadcasting auth endpoint
 */
window.aiChat = function (config) {
    return {
        messages: [],
        prompt: '',
        loading: false,
        streaming: false,
        error: null,
        _ws: null,
        _currentResponse: '',

        send() {
            if (!this.prompt.trim() || this.loading) return;

            const userPrompt = this.prompt.trim();
            this.prompt = '';
            this.error = null;
            this.loading = true;

            this.messages.push({ role: 'user', content: userPrompt, timestamp: this._now() });
            this.$nextTick(() => this.scrollToBottom());

            this._dispatchAndStream(userPrompt);
        },

        async _dispatchAndStream(prompt) {
            try {
                const response = await fetch(config.askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    credentials: 'include',
                    body: JSON.stringify({ prompt }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || `Request failed (${response.status})`);
                }

                this._connectToStream(data.stream_id, data.channel);
            } catch (e) {
                this.error = e.message;
                this.loading = false;
            }
        },

        _connectToStream(streamId, channel) {
            const wsScheme = config.reverbScheme === 'https' ? 'wss' : 'ws';
            const wsUrl = `${wsScheme}://${config.reverbHost}:${config.reverbPort}/app/${config.reverbKey}?protocol=7`;

            this._ws = new WebSocket(wsUrl);
            this._currentResponse = '';
            this.streaming = true;

            // Add assistant message placeholder with tools array
            this.messages.push({ role: 'assistant', content: '', tools: [], timestamp: this._now(), agent_name: this._agentName() });
            const msgIndex = this.messages.length - 1;

            this._ws.onopen = () => {};

            this._ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);

                switch (msg.event) {
                    case 'pusher:connection_established': {
                        const socketId = JSON.parse(msg.data).socket_id;
                        this._subscribeToPrivateChannel(channel, socketId);
                        break;
                    }
                    case 'pusher_internal:subscription_succeeded':
                        break;
                    case 'ai.started':
                        break;
                    case 'ai.tool_call': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        if (payload.tools && Array.isArray(payload.tools)) {
                            payload.tools.forEach(tool => {
                                this.messages[msgIndex].tools.push({
                                    name: tool.name,
                                    inputs: tool.inputs || {},
                                });
                            });
                        }
                        this.$nextTick(() => this.scrollToBottom());
                        break;
                    }
                    case 'ai.token': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        this._currentResponse += payload.token;
                        this.messages[msgIndex].content = this._currentResponse;
                        this.$nextTick(() => this.scrollToBottom());
                        break;
                    }
                    case 'ai.completed':
                        this._cleanup();
                        break;
                    case 'ai.failed': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        this.error = payload.error || 'Stream failed.';
                        // Remove empty assistant message if no tokens arrived
                        if (!this._currentResponse) {
                            this.messages.splice(msgIndex, 1);
                        }
                        this._cleanup();
                        break;
                    }
                }
            };

            this._ws.onerror = () => {
                this.error = 'WebSocket connection error.';
                this._cleanup();
            };

            this._ws.onclose = () => {
                if (this.streaming) {
                    this._cleanup();
                }
            };
        },

        async _subscribeToPrivateChannel(channel, socketId) {
            try {
                const authResponse = await fetch(config.authUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel,
                    }),
                });

                if (!authResponse.ok) {
                    throw new Error('Channel authorization failed');
                }

                const authData = await authResponse.json();

                this._ws.send(JSON.stringify({
                    event: 'pusher:subscribe',
                    data: {
                        channel: channel,
                        auth: authData.auth,
                    },
                }));
            } catch (e) {
                this.error = e.message;
                this._cleanup();
            }
        },

        _cleanup() {
            this.loading = false;
            this.streaming = false;
            if (this._ws) {
                this._ws.close();
                this._ws = null;
            }
        },

        scrollToBottom() {
            const container = this.$refs.chatContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },

        destroy() {
            this._cleanup();
        },
    };
};

/**
 * Navigation Switcher — Alpine.js component for sidebar ↔ topbar toggle.
 *
 * Reads/writes the user's preference to localStorage ('aicl_nav_layout').
 * Manipulates the `data-nav-mode` attribute on <html> and toggles the
 * `fi-body-has-top-navigation` class on the Filament body element.
 * Default is 'sidebar' when no preference is stored.
 *
 * Also persists sidebar collapse state to localStorage ('aicl_sidebar_collapsed')
 * so collapsing the sidebar survives page loads and topbar↔sidebar switches.
 */
window.navigationSwitcher = function () {
    return {
        mode: localStorage.getItem('aicl_nav_layout') || 'sidebar',
        _sidebarWatcher: null,

        init() {
            this.applyMode(this.mode);
            this._watchSidebarCollapse();
        },

        toggle() {
            this.mode = this.mode === 'sidebar' ? 'topbar' : 'sidebar';
            localStorage.setItem('aicl_nav_layout', this.mode);
            this.applyMode(this.mode);
        },

        applyMode(mode) {
            // Set on <html> for CSS selectors (matches the early-init script)
            document.documentElement.setAttribute('data-nav-mode', mode);

            // Do NOT toggle fi-body-has-top-navigation — Filament renders it
            // server-side via ->topNavigation(true) and CSS overrides in theme.css
            // handle visibility based on data-nav-mode. Toggling the class would
            // break the CSS selectors that depend on it.

            if (mode === 'topbar') {
                // Close sidebar on desktop when switching to topbar — but do NOT
                // overwrite the persisted collapse state (already saved by watcher)
                if (window.innerWidth >= 1024 && window.Alpine && Alpine.store('sidebar')) {
                    Alpine.store('sidebar').close();
                }
            } else {
                // Restore sidebar collapse preference when switching back to sidebar
                if (window.innerWidth >= 1024 && window.Alpine && Alpine.store('sidebar')) {
                    var wasCollapsed = localStorage.getItem('aicl_sidebar_collapsed') === 'true';
                    if (wasCollapsed) {
                        Alpine.store('sidebar').close();
                    } else {
                        Alpine.store('sidebar').open();
                    }
                }
                // Remove the early-init data attribute — Alpine is now in control
                document.documentElement.removeAttribute('data-sidebar-collapsed');
            }
        },

        /**
         * Watch Filament's sidebar store for collapse/expand changes and persist
         * to localStorage. Only tracks changes on desktop (>= 1024px) since
         * mobile sidebar is a drawer, not a collapsible rail.
         */
        _watchSidebarCollapse() {
            var setupWatcher = function () {
                if (!window.Alpine || !Alpine.store('sidebar')) return false;

                var store = Alpine.store('sidebar');

                // Sync initial state
                if (window.innerWidth >= 1024) {
                    localStorage.setItem('aicl_sidebar_collapsed', !store.isOpen ? 'true' : 'false');
                }

                // Remove early-init attribute now that Alpine controls the sidebar
                document.documentElement.removeAttribute('data-sidebar-collapsed');

                // Watch for changes using Alpine.effect
                Alpine.effect(function () {
                    var isOpen = Alpine.store('sidebar').isOpen;

                    if (window.innerWidth >= 1024) {
                        var currentMode = localStorage.getItem('aicl_nav_layout') || 'sidebar';
                        if (currentMode === 'sidebar') {
                            localStorage.setItem('aicl_sidebar_collapsed', !isOpen ? 'true' : 'false');
                        }
                    }
                });

                return true;
            };

            // Try immediately (store may already exist if Alpine booted before this script)
            if (setupWatcher()) return;

            // Try on alpine:initialized event
            document.addEventListener('alpine:initialized', function () {
                requestAnimationFrame(function () { setupWatcher(); });
            });

            // Fallback: poll briefly in case both above missed the window
            var attempts = 0;
            var poll = setInterval(function () {
                attempts++;
                if (setupWatcher() || attempts > 20) {
                    clearInterval(poll);
                }
            }, 100);
        },
    };
};

/**
 * AI Assistant Panel — Livewire + Alpine.js floating chat widget.
 *
 * Manages panel state (open/close, compact/full-screen), conversation switching,
 * and WebSocket streaming for multi-turn AI conversations via Livewire backend.
 */
window.aiAssistantPanel = function (config) {
    return {
        panelOpen: false,
        fullScreen: false,
        sidebarOpen: true,
        messages: [],
        prompt: '',
        loading: false,
        streaming: false,
        error: null,
        _ws: null,
        _currentResponse: '',
        activeConversationId: null,
        selectedAgentId: null,

        _now() {
            return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        },

        _agentName() {
            const select = this.$el.querySelector('select');
            if (select && select.selectedIndex >= 0) {
                return select.options[select.selectedIndex].text;
            }
            return 'Assistant';
        },

        init() {
            // Sync initial state from Livewire
            this.activeConversationId = this.$wire.activeConversationId;
            this.selectedAgentId = this.$wire.selectedAgentId;

            // Set default agent if none selected
            const agentSelect = this.$el.querySelector('select');
            if (!this.selectedAgentId && agentSelect && agentSelect.options.length > 0) {
                this.selectedAgentId = agentSelect.options[0].value;
            }
        },

        togglePanel() {
            this.panelOpen = !this.panelOpen;
            if (this.panelOpen) {
                this.$nextTick(() => {
                    if (this.$refs.promptInput) {
                        this.$refs.promptInput.focus();
                    }
                });
            }
        },

        closePanel() {
            this.panelOpen = false;
            this.fullScreen = false;
        },

        toggleFullScreen() {
            this.fullScreen = !this.fullScreen;
        },

        async newConversation() {
            this.messages = [];
            this._currentResponse = '';
            this.error = null;
            this.activeConversationId = null;
            await this.$wire.set('activeConversationId', null);
        },

        async switchConversation(id) {
            if (id === this.activeConversationId) return;

            this.activeConversationId = id;
            this._currentResponse = '';
            this.error = null;

            await this.$wire.switchConversation(id);
            this.selectedAgentId = this.$wire.selectedAgentId;

            const loaded = await this.$wire.loadMessages();
            this.messages = loaded.map(msg => ({
                role: msg.role,
                content: msg.content,
                tools: msg.tools || [],
                timestamp: msg.timestamp || '',
                agent_name: msg.agent_name || null,
            }));

            this.$nextTick(() => this.scrollToBottom());
        },

        async deleteConversation(id) {
            await this.$wire.deleteConversation(id);

            if (this.activeConversationId === id) {
                this.activeConversationId = null;
                this.messages = [];
            }
        },

        onAgentChange() {
            this.$wire.set('selectedAgentId', this.selectedAgentId);
            // Reset conversation when agent changes
            this.messages = [];
            this.activeConversationId = null;
            this.$wire.set('activeConversationId', null);
        },

        async send() {
            if (!this.prompt.trim() || this.loading) return;

            const userPrompt = this.prompt.trim();
            this.prompt = '';
            this.error = null;
            this.loading = true;

            this.messages.push({ role: 'user', content: userPrompt, tools: [], timestamp: this._now() });
            this.$nextTick(() => this.scrollToBottom());

            // Reset textarea height
            if (this.$refs.promptInput) {
                this.$refs.promptInput.style.height = 'auto';
            }

            try {
                // Sync agent selection before sending
                await this.$wire.set('selectedAgentId', this.selectedAgentId);

                const result = await this.$wire.sendMessage(userPrompt);

                if (result.error) {
                    this.error = result.error;
                    this.loading = false;
                    return;
                }

                // Update active conversation ID from Livewire
                this.activeConversationId = this.$wire.activeConversationId;

                this._connectToStream(result.stream_id, result.channel);
            } catch (e) {
                this.error = e.message || 'Failed to send message.';
                this.loading = false;
            }
        },

        _connectToStream(streamId, channel) {
            const wsScheme = config.reverbScheme === 'https' ? 'wss' : 'ws';
            const wsUrl = `${wsScheme}://${config.reverbHost}:${config.reverbPort}/app/${config.reverbKey}?protocol=7`;

            this._ws = new WebSocket(wsUrl);
            this._currentResponse = '';
            this.streaming = true;

            this.messages.push({ role: 'assistant', content: '', tools: [], timestamp: this._now(), agent_name: this._agentName() });
            const msgIndex = this.messages.length - 1;

            this._ws.onopen = () => {};

            this._ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);

                switch (msg.event) {
                    case 'pusher:connection_established': {
                        const socketId = JSON.parse(msg.data).socket_id;
                        this._subscribeToPrivateChannel(channel, socketId);
                        break;
                    }
                    case 'pusher_internal:subscription_succeeded':
                        break;
                    case 'ai.started':
                        break;
                    case 'ai.tool_call': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        if (payload.tools && Array.isArray(payload.tools)) {
                            payload.tools.forEach(tool => {
                                this.messages[msgIndex].tools.push({
                                    name: tool.name,
                                    inputs: tool.inputs || {},
                                    render: tool.render || null,
                                });
                            });
                        }
                        this.$nextTick(() => this.scrollToBottom());
                        break;
                    }
                    case 'ai.token': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        this._currentResponse += payload.token;
                        this.messages[msgIndex].content = this._stripToolCallJson(this._currentResponse);
                        this.$nextTick(() => this.scrollToBottom());
                        break;
                    }
                    case 'ai.completed':
                        // Final cleanup of tool call JSON from the completed response
                        if (this._currentResponse) {
                            this.messages[msgIndex].content = this._stripToolCallJson(this._currentResponse);
                        }
                        this._cleanup();
                        break;
                    case 'ai.failed': {
                        const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
                        this.error = payload.error || 'Stream failed.';
                        if (!this._currentResponse) {
                            this.messages.splice(msgIndex, 1);
                        }
                        this._cleanup();
                        break;
                    }
                }
            };

            this._ws.onerror = () => {
                this.error = 'WebSocket connection error.';
                this._cleanup();
            };

            this._ws.onclose = () => {
                if (this.streaming) {
                    this._cleanup();
                }
            };
        },

        async _subscribeToPrivateChannel(channel, socketId) {
            try {
                const authResponse = await fetch(config.authUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel,
                    }),
                });

                if (!authResponse.ok) {
                    throw new Error('Channel authorization failed');
                }

                const authData = await authResponse.json();

                this._ws.send(JSON.stringify({
                    event: 'pusher:subscribe',
                    data: {
                        channel: channel,
                        auth: authData.auth,
                    },
                }));
            } catch (e) {
                this.error = e.message;
                this._cleanup();
            }
        },

        _cleanup() {
            this.loading = false;
            this.streaming = false;
            if (this._ws) {
                this._ws.close();
                this._ws = null;
            }
        },

        /**
         * Strip leading tool call JSON from streamed response text.
         *
         * NeuronAI streams tool call/result data as a JSON array before
         * the natural language response: [{callId, name, ...}]Text here.
         * This removes the JSON portion and returns the clean text.
         */
        _stripToolCallJson(text) {
            const trimmed = text.trimStart();
            if (!trimmed.startsWith('[{')) return text;

            let depth = 0;
            let inString = false;
            let escape = false;

            for (let i = 0; i < trimmed.length; i++) {
                const ch = trimmed[i];
                if (escape) { escape = false; continue; }
                if (ch === '\\' && inString) { escape = true; continue; }
                if (ch === '"') { inString = !inString; continue; }
                if (inString) continue;
                if (ch === '[') depth++;
                if (ch === ']') {
                    depth--;
                    if (depth === 0) {
                        const remaining = trimmed.substring(i + 1).trimStart();
                        return remaining || '';
                    }
                }
            }

            // JSON array not yet closed — hide the partial JSON while streaming
            return '';
        },

        /**
         * Check if the current response is still buffering tool call JSON.
         * Returns true while the opening [{ hasn't been closed yet.
         */
        _isBufferingJson(text) {
            if (!text) return false;
            const trimmed = text.trimStart();
            if (!trimmed.startsWith('[{')) return false;

            let depth = 0;
            let inString = false;
            let escape = false;

            for (let i = 0; i < trimmed.length; i++) {
                const ch = trimmed[i];
                if (escape) { escape = false; continue; }
                if (ch === '\\' && inString) { escape = true; continue; }
                if (ch === '"') { inString = !inString; continue; }
                if (inString) continue;
                if (ch === '[') depth++;
                if (ch === ']') { depth--; if (depth === 0) return false; }
            }

            return true;
        },

        /**
         * Render markdown to sanitized HTML.
         * Falls back to basic newline→br if marked/DOMPurify aren't loaded.
         */
        _renderMarkdown(text) {
            if (!text) return '';
            if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
                try {
                    return DOMPurify.sanitize(marked.parse(text, { breaks: true }));
                } catch (_e) {
                    // Fall through to basic rendering
                }
            }
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
        },

        /**
         * Render a structured tool result card as HTML.
         * Returns empty string if no render data or type is 'text'.
         */
        _renderToolCard(tool) {
            if (!tool.render || !tool.render.data) return '';
            const { type, data } = tool.render;

            if (type === 'text' || typeof data === 'string') return '';

            if (type === 'table' && data.columns && data.rows) {
                const ths = data.columns.map(c => `<th class="px-3 py-1.5 text-left text-xs font-medium text-gray-400">${this._esc(c)}</th>`).join('');
                const trs = data.rows.map(row => {
                    const tds = data.columns.map(c => `<td class="px-3 py-1.5 text-xs text-gray-300">${this._esc(String(row[c] ?? '-'))}</td>`).join('');
                    return `<tr class="border-t border-white/5">${tds}</tr>`;
                }).join('');
                return `<div class="overflow-x-auto rounded-lg border border-white/5 bg-gray-800/30"><table class="w-full"><thead><tr class="border-b border-white/10">${ths}</tr></thead><tbody>${trs}</tbody></table></div>`;
            }

            if (type === 'key-value' && data.pairs) {
                const items = data.pairs.map(p =>
                    `<div class="flex justify-between gap-4 py-1.5"><span class="text-xs text-gray-400">${this._esc(p.key)}</span><span class="text-xs font-medium text-gray-200">${this._esc(String(p.value))}</span></div>`
                ).join('');
                return `<div class="rounded-lg border border-white/5 bg-gray-800/30 px-3 divide-y divide-white/5">${items}</div>`;
            }

            if (type === 'status' && data.items) {
                const badges = data.items.map(item => {
                    const color = item.status === 'healthy' ? 'text-green-400 bg-green-400/10 ring-green-400/20'
                        : item.status === 'degraded' ? 'text-yellow-400 bg-yellow-400/10 ring-yellow-400/20'
                        : 'text-red-400 bg-red-400/10 ring-red-400/20';
                    return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${color}"><span class="h-1.5 w-1.5 rounded-full" style="background: currentColor"></span>${this._esc(item.label)}</span>`;
                }).join('');
                return `<div class="flex flex-wrap gap-1.5">${badges}</div>`;
            }

            return '';
        },

        /**
         * Escape HTML entities for safe rendering in tool cards.
         */
        _esc(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        scrollToBottom() {
            const container = this.$refs.chatContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },

        handleGlobalKeydown(e) {
            // Parse keyboard shortcut
            const shortcut = config.keyboardShortcut || 'cmd+j';
            const parts = shortcut.toLowerCase().split('+');
            const key = parts.pop();
            const needsCmd = parts.includes('cmd') || parts.includes('meta');
            const needsCtrl = parts.includes('ctrl');

            const modifierMatch = (needsCmd && e.metaKey) || (needsCtrl && e.ctrlKey);
            if (modifierMatch && e.key.toLowerCase() === key) {
                e.preventDefault();
                this.togglePanel();
            }

            // Escape closes panel
            if (e.key === 'Escape' && this.panelOpen) {
                this.closePanel();
            }
        },

        destroy() {
            this._cleanup();
        },
    };
};

window.presenceIndicator = function ({ channelName }) {
    return {
        viewers: [],

        init() {
            if (!window.Echo || !channelName) return;

            window.Echo.join(channelName)
                .here((users) => {
                    this.viewers = users;
                })
                .joining((user) => {
                    this.viewers.push(user);
                })
                .leaving((user) => {
                    this.viewers = this.viewers.filter(v => v.id !== user.id);
                });
        },

        destroy() {
            if (window.Echo && channelName) {
                window.Echo.leave(channelName);
            }
        },
    };
};
