/**
 * AICL Toast — Alpine.js global store for toast notifications.
 *
 * Usage: Alpine.store('toasts').add({ type: 'success', title: 'Saved!', message: 'Details...', duration: 5000 })
 *
 * Features: auto-dismiss with progress bar, stacking with max visible,
 * type-based styling (success/info/warning/error).
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        _nextId: 1,

        /**
         * Add a toast notification.
         *
         * @param {Object} toast
         * @param {string} toast.type - success|info|warning|error
         * @param {string} toast.title - Toast title
         * @param {string} [toast.message] - Optional message body
         * @param {number} [toast.duration=5000] - Auto-dismiss in ms (0 = manual only)
         */
        add(toast) {
            const id = this._nextId++;
            const item = {
                id,
                type: toast.type || 'info',
                title: toast.title || '',
                message: toast.message || '',
                duration: toast.duration !== undefined ? toast.duration : 5000,
                show: true,
                createdAt: Date.now(),
            };

            this.items.push(item);

            if (item.duration > 0) {
                setTimeout(() => this.remove(id), item.duration);
            }
        },

        /**
         * Remove a toast by ID.
         */
        remove(id) {
            const index = this.items.findIndex(t => t.id === id);
            if (index !== -1) {
                this.items[index].show = false;
                setTimeout(() => {
                    this.items = this.items.filter(t => t.id !== id);
                }, 300);
            }
        },

        /**
         * Get visible toasts (newest first, limited).
         */
        visible(max = 5) {
            return this.items
                .filter(t => t.show)
                .slice(-max)
                .reverse();
        },
    });
});
