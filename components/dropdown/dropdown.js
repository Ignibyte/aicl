/**
 * AICL Dropdown — Alpine.js component with Floating UI positioning.
 *
 * Features: keyboard navigation (arrow keys), focus management,
 * auto-positioning via Floating UI, reduced-motion support.
 */
import { setupFloating } from '../_shared/floating.js';

window.aiclDropdown = function ({ placement, closeOnClick }) {
    return {
        isOpen: false,
        _cleanup: null,
        _activeIndex: -1,

        toggle() {
            this.isOpen ? this.close() : this.open();
        },

        open() {
            this.isOpen = true;
            this._activeIndex = -1;

            this.$nextTick(() => {
                this._cleanup = setupFloating(this.$refs.trigger, this.$refs.panel, {
                    placement,
                    offsetDistance: 4,
                });
            });
        },

        close() {
            this.isOpen = false;
            this._activeIndex = -1;

            if (this._cleanup) {
                this._cleanup();
                this._cleanup = null;
            }
        },

        getMenuItems() {
            return [...this.$refs.panel.querySelectorAll('[role="menuitem"]:not([disabled])')];
        },

        handleKeydown(e) {
            if (!this.isOpen) return;

            const items = this.getMenuItems();
            if (items.length === 0) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this._activeIndex = Math.min(this._activeIndex + 1, items.length - 1);
                    items[this._activeIndex]?.focus();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this._activeIndex = Math.max(this._activeIndex - 1, 0);
                    items[this._activeIndex]?.focus();
                    break;
                case 'Home':
                    e.preventDefault();
                    this._activeIndex = 0;
                    items[0]?.focus();
                    break;
                case 'End':
                    e.preventDefault();
                    this._activeIndex = items.length - 1;
                    items[this._activeIndex]?.focus();
                    break;
                case 'Enter':
                case ' ':
                    if (this._activeIndex >= 0) {
                        e.preventDefault();
                        items[this._activeIndex]?.click();
                    }
                    break;
            }
        },

        destroy() {
            if (this._cleanup) {
                this._cleanup();
                this._cleanup = null;
            }
        },
    };
};
