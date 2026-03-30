/**
 * AICL Modal — Alpine.js component for dialog overlays.
 *
 * Features: focus trap, body scroll lock, return focus on close,
 * reduced-motion support via CSS transitions.
 */
window.aiclModal = function ({ closeable, closeOnEscape: _closeOnEscape, closeOnClickOutside: _closeOnClickOutside, trapFocus }) {
    return {
        isOpen: false,
        _previouslyFocused: null,
        _focusableSelector: 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',

        open() {
            this._previouslyFocused = document.activeElement;
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');

            if (trapFocus) {
                this.$nextTick(() => {
                    const panel = this.$refs.panel;
                    if (panel) {
                        const first = panel.querySelector(this._focusableSelector);
                        if (first) first.focus();
                    }
                });
            }
        },

        close() {
            if (!closeable) return;

            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');

            if (this._previouslyFocused) {
                this.$nextTick(() => {
                    this._previouslyFocused.focus();
                    this._previouslyFocused = null;
                });
            }
        },

        handleTab(e) {
            if (!trapFocus || !this.isOpen) return;

            const panel = this.$refs.panel;
            if (!panel) return;

            const focusable = [...panel.querySelectorAll(this._focusableSelector)];
            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },

        destroy() {
            if (this.isOpen) {
                document.body.classList.remove('overflow-hidden');
            }
        },
    };
};
