/**
 * AICL Tooltip — Alpine.js component with Floating UI positioning.
 *
 * Features: configurable delay, arrow positioning,
 * auto-flip on viewport edge, reduced-motion support.
 */
import { setupFloating } from '../_shared/floating.js';

window.aiclTooltip = function ({ position, delay }) {
    return {
        isVisible: false,
        _cleanup: null,
        _timer: null,

        show() {
            this._timer = setTimeout(() => {
                this.isVisible = true;

                this.$nextTick(() => {
                    this._cleanup = setupFloating(this.$refs.trigger, this.$refs.tooltip, {
                        placement: position,
                        offsetDistance: 8,
                        arrowElement: this.$refs.arrow,
                    });
                });
            }, delay);
        },

        hide() {
            if (this._timer) {
                clearTimeout(this._timer);
                this._timer = null;
            }

            this.isVisible = false;

            if (this._cleanup) {
                this._cleanup();
                this._cleanup = null;
            }
        },

        destroy() {
            this.hide();
        },
    };
};
