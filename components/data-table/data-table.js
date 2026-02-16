/**
 * AICL DataTable — Alpine.js component for client-side data tables.
 *
 * Features: column sorting, search filtering, pagination, row selection,
 * per-page control, empty state.
 */
window.aiclDataTable = function ({ columns, data, sortable, filterable, paginated, perPage, perPageOptions, selectable }) {
    return {
        columns,
        allData: data,
        sortable,
        filterable,
        paginated,
        perPageOptions,
        selectable,

        search: '',
        sortKey: '',
        sortDir: 'asc',
        currentPage: 1,
        pageSize: perPage,
        selected: [],

        get filteredData() {
            let result = [...this.allData];

            // Search filter
            if (this.search.trim()) {
                const q = this.search.trim().toLowerCase();
                const stringCols = this.columns
                    .filter(c => c.filterable !== false)
                    .map(c => c.key);

                result = result.filter(row =>
                    stringCols.some(key => {
                        const val = row[key];
                        return val != null && String(val).toLowerCase().includes(q);
                    })
                );
            }

            // Sort
            if (this.sortKey) {
                result.sort((a, b) => {
                    const aVal = a[this.sortKey] ?? '';
                    const bVal = b[this.sortKey] ?? '';

                    if (typeof aVal === 'number' && typeof bVal === 'number') {
                        return this.sortDir === 'asc' ? aVal - bVal : bVal - aVal;
                    }

                    const cmp = String(aVal).localeCompare(String(bVal));
                    return this.sortDir === 'asc' ? cmp : -cmp;
                });
            }

            return result;
        },

        get paginatedData() {
            if (!this.paginated) return this.filteredData;

            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredData.slice(start, start + this.pageSize);
        },

        get totalPages() {
            if (!this.paginated) return 1;
            return Math.max(1, Math.ceil(this.filteredData.length / this.pageSize));
        },

        get pageStart() {
            if (this.filteredData.length === 0) return 0;
            return (this.currentPage - 1) * this.pageSize + 1;
        },

        get pageEnd() {
            return Math.min(this.currentPage * this.pageSize, this.filteredData.length);
        },

        get allSelected() {
            return this.paginatedData.length > 0 &&
                this.paginatedData.every((_, i) => this.selected.includes(i));
        },

        sort(key) {
            if (!this.sortable) return;

            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
            this.currentPage = 1;
        },

        toggleAll() {
            if (this.allSelected) {
                this.selected = [];
            } else {
                this.selected = this.paginatedData.map((_, i) => i);
            }
        },

        toggleRow(index) {
            const pos = this.selected.indexOf(index);
            if (pos === -1) {
                this.selected.push(index);
            } else {
                this.selected.splice(pos, 1);
            }
        },
    };
};
