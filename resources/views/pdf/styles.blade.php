<style>
    /* Reset and Base */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #1f2937;
    }

    /* Page Setup */
    @page {
        margin: 20mm 15mm 25mm 15mm;
    }

    /* Header */
    .pdf-header {
        position: fixed;
        top: -15mm;
        left: 0;
        right: 0;
        height: 15mm;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 5mm;
    }

    .pdf-header table {
        width: 100%;
    }

    .pdf-header .logo {
        font-size: 16px;
        font-weight: bold;
        color: #4f46e5;
    }

    .pdf-header .date {
        text-align: right;
        font-size: 10px;
        color: #6b7280;
    }

    /* Footer */
    .pdf-footer {
        position: fixed;
        bottom: -20mm;
        left: 0;
        right: 0;
        height: 15mm;
        border-top: 1px solid #e5e7eb;
        padding-top: 5mm;
        font-size: 10px;
        color: #6b7280;
    }

    .pdf-footer table {
        width: 100%;
    }

    .pdf-footer .page-number:after {
        content: counter(page);
    }

    /* Main Content */
    .pdf-content {
        margin-top: 5mm;
    }

    /* Typography */
    h1 {
        font-size: 24px;
        font-weight: bold;
        color: #111827;
        margin-bottom: 10px;
    }

    h2 {
        font-size: 18px;
        font-weight: bold;
        color: #1f2937;
        margin-top: 15px;
        margin-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 5px;
    }

    h3 {
        font-size: 14px;
        font-weight: bold;
        color: #374151;
        margin-top: 10px;
        margin-bottom: 5px;
    }

    p {
        margin-bottom: 8px;
    }

    /* Tables */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    th, td {
        border: 1px solid #e5e7eb;
        padding: 8px 10px;
        text-align: left;
        vertical-align: top;
    }

    th {
        background-color: #f9fafb;
        font-weight: bold;
        color: #374151;
        font-size: 11px;
        text-transform: uppercase;
    }

    tr:nth-child(even) {
        background-color: #f9fafb;
    }

    /* Info Grid (2 column layout) */
    .info-grid {
        width: 100%;
        margin-bottom: 15px;
    }

    .info-grid td {
        border: none;
        padding: 5px 10px 5px 0;
        width: 50%;
    }

    .info-grid .label {
        color: #6b7280;
        font-size: 11px;
        font-weight: normal;
    }

    .info-grid .value {
        color: #111827;
        font-weight: 500;
    }

    /* Status Badges */
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .badge-draft { background-color: #e5e7eb; color: #374151; }
    .badge-planning { background-color: #dbeafe; color: #1e40af; }
    .badge-active { background-color: #dcfce7; color: #166534; }
    .badge-on_hold { background-color: #fef3c7; color: #92400e; }
    .badge-completed { background-color: #d1fae5; color: #065f46; }
    .badge-cancelled { background-color: #fee2e2; color: #991b1b; }

    .badge-low { background-color: #e5e7eb; color: #374151; }
    .badge-medium { background-color: #fef3c7; color: #92400e; }
    .badge-high { background-color: #fed7aa; color: #c2410c; }
    .badge-critical { background-color: #fee2e2; color: #991b1b; }

    /* Cards/Sections */
    .card {
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
        background-color: #ffffff;
    }

    .card-header {
        font-weight: bold;
        font-size: 14px;
        color: #1f2937;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
    }

    /* Stats Row */
    .stats-row {
        width: 100%;
        margin-bottom: 15px;
    }

    .stats-row td {
        border: 1px solid #e5e7eb;
        padding: 10px 15px;
        text-align: center;
        background-color: #f9fafb;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #4f46e5;
    }

    .stat-label {
        font-size: 10px;
        color: #6b7280;
        text-transform: uppercase;
    }

    /* Timeline */
    .timeline-item {
        padding-left: 20px;
        border-left: 2px solid #e5e7eb;
        margin-left: 10px;
        margin-bottom: 10px;
        position: relative;
    }

    .timeline-item::before {
        content: '';
        width: 8px;
        height: 8px;
        background-color: #4f46e5;
        border-radius: 50%;
        position: absolute;
        left: -5px;
        top: 5px;
    }

    .timeline-date {
        font-size: 10px;
        color: #6b7280;
    }

    .timeline-content {
        font-size: 12px;
        color: #374151;
    }

    /* Utilities */
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-muted { color: #6b7280; }
    .text-small { font-size: 10px; }
    .mt-10 { margin-top: 10px; }
    .mb-10 { margin-bottom: 10px; }
    .page-break { page-break-after: always; }
</style>
