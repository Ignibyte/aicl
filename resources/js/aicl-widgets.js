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

            this.messages.push({ role: 'user', content: userPrompt });
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
            this.messages.push({ role: 'assistant', content: '', tools: [] });
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
 */
window.navigationSwitcher = function () {
    return {
        mode: localStorage.getItem('aicl_nav_layout') || 'sidebar',

        init() {
            this.applyMode(this.mode);
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
                // Close sidebar on desktop when switching to topbar
                if (window.innerWidth >= 1024 && window.Alpine && Alpine.store('sidebar')) {
                    Alpine.store('sidebar').close();
                }
            } else {
                // Re-open sidebar on desktop when switching back
                if (window.innerWidth >= 1024 && window.Alpine && Alpine.store('sidebar')) {
                    Alpine.store('sidebar').open();
                }
            }
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
