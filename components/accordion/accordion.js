/**
 * AICL Accordion — Alpine.js component for collapsible content sections.
 *
 * Features: single/multi expand mode, keyboard navigation,
 * smooth height animation, reduced-motion support.
 */
window.aiclAccordion = function ({ allowMultiple, defaultOpen }) {
    return {
        openItems: [...defaultOpen],

        isOpen(name) {
            return this.openItems.includes(name);
        },

        toggle(name) {
            if (this.isOpen(name)) {
                this.openItems = this.openItems.filter(item => item !== name);
            } else {
                if (allowMultiple) {
                    this.openItems.push(name);
                } else {
                    this.openItems = [name];
                }
            }
        },
    };
};
