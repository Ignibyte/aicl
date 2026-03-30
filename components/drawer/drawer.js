/**
 * AICL Drawer — Alpine.js component for slide-over panels.
 *
 * Features: body scroll lock, focus trap, keyboard close,
 * reduced-motion support via CSS transitions.
 */
window.aiclDrawer = function ({ closeable, position: _position }) {
    return {
        isOpen: false,
        _previouslyFocused: null,
        _focusableSelector: 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',

        open() {
            this._previouslyFocused = document.activeElement;
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');

            this.$nextTick(() => {
                const panel = this.$refs.panel;
                if (panel) {
                    const first = panel.querySelector(this._focusableSelector);
                    if (first) first.focus();
                }
            });
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

        destroy() {
            if (this.isOpen) {
                document.body.classList.remove('overflow-hidden');
            }
        },
    };
};
