/**
 * AICL Command Palette — Alpine.js component for spotlight search.
 *
 * Features: Cmd+K/Ctrl+K global shortcut, keyboard navigation,
 * grouped results, optional async search, focus trap.
 */
window.aiclCommandPalette = function ({ items, groups, searchEndpoint: _searchEndpoint }) {
    return {
        isOpen: false,
        query: '',
        activeIndex: 0,
        allItems: items,
        _previouslyFocused: null,

        get activeId() {
            const flat = this.flatItems;
            return flat[this.activeIndex] ? 'cmd-item-' + flat[this.activeIndex].id : '';
        },

        get filteredItems() {
            if (!this.query.trim()) return this.allItems;

            const q = this.query.trim().toLowerCase();
            return this.allItems.filter(item => {
                const label = (item.label || '').toLowerCase();
                const keywords = (item.keywords || '').toLowerCase();
                return label.includes(q) || keywords.includes(q);
            });
        },

        get flatItems() {
            const flat = [];
            let index = 0;
            for (const group of this.groupedItems) {
                for (const item of group.items) {
                    item._flatIndex = index++;
                    flat.push(item);
                }
            }
            return flat;
        },

        get groupedItems() {
            const items = this.filteredItems;

            if (groups.length === 0) {
                return [{ key: '_all', label: '', items }];
            }

            const result = [];
            const grouped = {};

            for (const g of groups) {
                grouped[g.key] = { key: g.key, label: g.label, items: [] };
            }
            grouped._ungrouped = { key: '_ungrouped', label: '', items: [] };

            for (const item of items) {
                const gKey = item.group || '_ungrouped';
                if (grouped[gKey]) {
                    grouped[gKey].items.push(item);
                } else {
                    grouped._ungrouped.items.push(item);
                }
            }

            for (const g of groups) {
                if (grouped[g.key].items.length > 0) {
                    result.push(grouped[g.key]);
                }
            }
            if (grouped._ungrouped.items.length > 0) {
                result.push(grouped._ungrouped);
            }

            return result;
        },

        handleGlobalKeydown(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.isOpen ? this.close() : this.open();
            }
        },

        open() {
            this._previouslyFocused = document.activeElement;
            this.isOpen = true;
            this.query = '';
            this.activeIndex = 0;
            document.body.classList.add('overflow-hidden');

            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },

        close() {
            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');

            if (this._previouslyFocused) {
                this.$nextTick(() => {
                    this._previouslyFocused.focus();
                    this._previouslyFocused = null;
                });
            }
        },

        moveDown() {
            const max = this.flatItems.length - 1;
            this.activeIndex = Math.min(this.activeIndex + 1, max);
            this.scrollToActive();
        },

        moveUp() {
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.scrollToActive();
        },

        scrollToActive() {
            const el = document.getElementById(this.activeId);
            if (el) el.scrollIntoView({ block: 'nearest' });
        },

        select(item) {
            if (item.action && typeof item.action === 'function') {
                item.action();
            } else if (item.url) {
                window.location.href = item.url;
            }
            this.$dispatch('command-selected', { item });
            this.close();
        },

        selectActive() {
            const flat = this.flatItems;
            if (flat[this.activeIndex]) {
                this.select(flat[this.activeIndex]);
            }
        },

        destroy() {
            if (this.isOpen) {
                document.body.classList.remove('overflow-hidden');
            }
        },
    };
};
