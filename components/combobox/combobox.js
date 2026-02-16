/**
 * AICL Combobox — Alpine.js searchable select with single/multi mode.
 *
 * Features: type-ahead search, keyboard navigation, multi-select tags,
 * optional async remote search, Floating UI-ready positioning.
 */
window.aiclCombobox = function ({ options, value, searchable, multiple, clearable, disabled, searchEndpoint }) {
    return {
        allOptions: options,
        search: '',
        isOpen: false,
        activeIndex: 0,
        loading: false,
        multiple,
        clearable,
        disabled,
        searchable,

        // Selection state
        selectedValue: multiple ? null : (value || null),
        selectedValues: multiple ? (Array.isArray(value) ? [...value] : []) : [],

        get activeId() {
            const opts = this.filteredOptions;
            return opts[this.activeIndex] ? 'combobox-opt-' + opts[this.activeIndex].value : '';
        },

        get displayPlaceholder() {
            if (this.multiple && this.selectedValues.length > 0) return '';
            if (!this.multiple && this.selectedValue) {
                const opt = this.allOptions.find(o => o.value === this.selectedValue);
                return opt ? opt.label : '';
            }
            return this.isOpen ? '' : '{{ placeholder }}';
        },

        get hasSelection() {
            return this.multiple ? this.selectedValues.length > 0 : this.selectedValue != null;
        },

        get selectedOptions() {
            if (!this.multiple) return [];
            return this.selectedValues
                .map(v => this.allOptions.find(o => o.value === v))
                .filter(Boolean);
        },

        get filteredOptions() {
            if (!this.search.trim()) return this.allOptions;
            const q = this.search.trim().toLowerCase();
            return this.allOptions.filter(o =>
                o.label.toLowerCase().includes(q)
            );
        },

        isSelected(val) {
            return this.multiple
                ? this.selectedValues.includes(val)
                : this.selectedValue === val;
        },

        toggle() {
            this.isOpen ? this.close() : this.open();
        },

        open() {
            if (this.disabled) return;
            this.isOpen = true;
            this.activeIndex = 0;
            this.search = '';

            this.$nextTick(() => {
                this.$refs.input?.focus();
            });

            if (searchEndpoint && !this.search) {
                this.fetchRemote('');
            }
        },

        close() {
            this.isOpen = false;
            this.search = '';
        },

        select(opt) {
            if (opt.disabled) return;

            if (this.multiple) {
                if (this.selectedValues.includes(opt.value)) {
                    this.selectedValues = this.selectedValues.filter(v => v !== opt.value);
                } else {
                    this.selectedValues.push(opt.value);
                }
                this.search = '';
                this.$dispatch('change', { value: [...this.selectedValues] });
            } else {
                this.selectedValue = opt.value;
                this.search = '';
                this.close();
                this.$dispatch('change', { value: opt.value });
            }
        },

        deselect(val) {
            this.selectedValues = this.selectedValues.filter(v => v !== val);
            this.$dispatch('change', { value: [...this.selectedValues] });
        },

        clearSelection() {
            if (this.multiple) {
                this.selectedValues = [];
                this.$dispatch('change', { value: [] });
            } else {
                this.selectedValue = null;
                this.$dispatch('change', { value: null });
            }
        },

        moveDown() {
            const max = this.filteredOptions.length - 1;
            this.activeIndex = Math.min(this.activeIndex + 1, max);
        },

        moveUp() {
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
        },

        selectActive() {
            const opts = this.filteredOptions;
            if (opts[this.activeIndex]) {
                this.select(opts[this.activeIndex]);
            }
        },

        async fetchRemote(query) {
            if (!searchEndpoint) return;

            this.loading = true;
            try {
                const url = new URL(searchEndpoint, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include',
                });
                if (response.ok) {
                    this.allOptions = await response.json();
                }
            } catch (e) {
                console.error('Combobox fetch error:', e);
            } finally {
                this.loading = false;
            }
        },
    };
};
