<div
    x-data="toolbarPresence()"
    x-init="connect()"
    @beforeunload.window="disconnect()"
    class="flex items-center"
>
    <template x-if="viewers.length > 0">
        <div class="flex items-center gap-1 mr-2" x-tooltip="viewerTooltip">
            {{-- Show up to 3 viewer badges --}}
            <template x-for="viewer in viewers.slice(0, 3)" :key="viewer.id">
                <span
                    class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 text-xs font-medium ring-2 ring-white dark:ring-gray-900"
                    x-text="initials(viewer.name)"
                ></span>
            </template>

            {{-- Overflow indicator --}}
            <template x-if="viewers.length > 3">
                <span
                    class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs font-medium ring-2 ring-white dark:ring-gray-900"
                    x-text="'+' + (viewers.length - 3)"
                ></span>
            </template>
        </div>
    </template>
</div>

<script>
    function toolbarPresence() {
        return {
            viewers: [],
            channel: null,
            currentHash: null,
            userId: @json(auth()->id()),

            get viewerTooltip() {
                if (this.viewers.length === 0) return '';
                const names = this.viewers.map(v => v.name).join(', ');
                return names + (this.viewers.length === 1 ? ' is' : ' are') + ' also viewing this page';
            },

            connect() {
                if (typeof window.Echo === 'undefined') {
                    return;
                }

                this.joinPageChannel();

                // Re-join on Livewire SPA navigation
                document.addEventListener('livewire:navigated', () => {
                    this.joinPageChannel();
                });
            },

            joinPageChannel() {
                const path = window.location.pathname;
                const hash = this.hashPath(path);

                if (hash === this.currentHash) {
                    return;
                }

                this.leaveCurrentChannel();

                this.currentHash = hash;
                this.channel = window.Echo.join('presence-page.' + hash)
                    .here((users) => {
                        this.viewers = users.filter(u => u.id !== this.userId);
                    })
                    .joining((user) => {
                        if (user.id !== this.userId) {
                            this.viewers.push(user);
                        }
                    })
                    .leaving((user) => {
                        this.viewers = this.viewers.filter(v => v.id !== user.id);
                    })
                    .error(() => {
                        this.viewers = [];
                    });
            },

            leaveCurrentChannel() {
                if (this.channel && this.currentHash) {
                    window.Echo.leave('presence-page.' + this.currentHash);
                    this.channel = null;
                    this.viewers = [];
                }
            },

            disconnect() {
                this.leaveCurrentChannel();
                this.currentHash = null;
            },

            hashPath(path) {
                // Simple hash for channel name — MD5 done server-side for auth,
                // client uses the same path string for consistency
                let hash = 0;
                for (let i = 0; i < path.length; i++) {
                    const char = path.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash |= 0;
                }
                return Math.abs(hash).toString(36);
            },

            initials(name) {
                if (!name) return '?';
                const parts = name.trim().split(/\s+/);
                if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
                return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
            }
        };
    }
</script>
