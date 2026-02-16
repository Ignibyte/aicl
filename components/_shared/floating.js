/**
 * AICL Floating UI — Shared positioning utility for dropdown, tooltip, combobox.
 *
 * Wraps @floating-ui/dom with auto-update, flip, shift, and offset middleware.
 */
import { computePosition, autoUpdate, flip, shift, offset, arrow } from '@floating-ui/dom';

/**
 * Position a floating element relative to a reference element.
 *
 * @param {HTMLElement} reference - The trigger element
 * @param {HTMLElement} floating - The floating panel/tooltip element
 * @param {Object} options
 * @param {string} options.placement - Floating UI placement (e.g. 'bottom-start')
 * @param {number} options.offsetDistance - Offset in px (default 4)
 * @param {HTMLElement|null} options.arrowElement - Arrow element for tooltip
 * @returns {Function} cleanup function to call on destroy
 */
export function setupFloating(reference, floating, options = {}) {
    const {
        placement = 'bottom-start',
        offsetDistance = 4,
        arrowElement = null,
    } = options;

    const middleware = [
        offset(offsetDistance),
        flip({ padding: 8 }),
        shift({ padding: 8 }),
    ];

    if (arrowElement) {
        middleware.push(arrow({ element: arrowElement }));
    }

    const cleanup = autoUpdate(reference, floating, () => {
        computePosition(reference, floating, {
            placement,
            middleware,
        }).then(({ x, y, placement: finalPlacement, middlewareData }) => {
            Object.assign(floating.style, {
                left: `${x}px`,
                top: `${y}px`,
            });

            if (arrowElement && middlewareData.arrow) {
                const { x: arrowX, y: arrowY } = middlewareData.arrow;
                const staticSide = {
                    top: 'bottom',
                    right: 'left',
                    bottom: 'top',
                    left: 'right',
                }[finalPlacement.split('-')[0]];

                Object.assign(arrowElement.style, {
                    left: arrowX != null ? `${arrowX}px` : '',
                    top: arrowY != null ? `${arrowY}px` : '',
                    right: '',
                    bottom: '',
                    [staticSide]: '-4px',
                });
            }

            floating.dataset.placement = finalPlacement;
        });
    });

    return cleanup;
}
