<style>
    :root {
        --report-accent: #940000;
        --report-border: #c0392b;
        --report-text: #2c3e50;
    }

    .official-report .report-sheet {
        background: #fff;
        padding: 32px;
        color: var(--report-text);
        border-radius: 8px;
    }

    .official-report .report-header-center { text-align: center; margin-bottom: 24px; }
    .official-report .report-header-center img { height: 56px; margin-bottom: 8px; border-radius: 8px; }
    .official-report .report-header-center h1 {
        font-size: 2rem;
        font-weight: 800;
        color: var(--report-accent);
        margin: 0;
        text-transform: uppercase;
    }
    .official-report .biz-contact-info { font-size: 0.9rem; color: #555; margin-top: 6px; }
    .official-report .operations-title { color: var(--report-accent); font-weight: 700; font-size: 1.1rem; margin-top: 8px; }
    .official-report .accent-divider { height: 3px; background: var(--report-accent); margin: 14px 0; border: none; }

    .official-report .report-sub-meta {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px 16px;
        font-size: 0.78rem;
        color: #777;
        margin-bottom: 8px;
    }

    .official-report .title-area { position: relative; text-align: center; margin: 20px 0 24px; }
    .official-report .main-report-title {
        font-size: 1.45rem;
        font-weight: 800;
        text-transform: uppercase;
        display: inline-block;
        border-bottom: 2px solid #555;
        padding-bottom: 4px;
    }
    .official-report .official-stamp {
        position: absolute;
        right: 8%;
        top: -8px;
        border: 3px solid #28a745;
        color: #28a745;
        padding: 4px 12px;
        font-weight: 900;
        font-size: 1.1rem;
        transform: rotate(-8deg);
        border-radius: 8px;
        opacity: 0.85;
        text-transform: uppercase;
        pointer-events: none;
    }
    .official-report .official-stamp.stamp-paid { border-color: #28a745; color: #28a745; }
    .official-report .official-stamp.stamp-pending { border-color: #e67e22; color: #e67e22; }
    .official-report .official-stamp.stamp-cancelled { border-color: #c0392b; color: #c0392b; }

    .official-report .btn-print {
        background: var(--report-accent);
        color: #fff;
        padding: 10px 24px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
    }
    .official-report .btn-print:hover { background: #7a0000; color: #fff; }

    .official-report .report-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    .official-report .stats-card-title {
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--report-accent);
        text-transform: uppercase;
        border-bottom: 2px solid var(--report-accent);
        padding-bottom: 5px;
        margin-bottom: 10px;
    }
    .official-report .stats-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 4px 0;
        font-size: 0.88rem;
    }

    .official-report .report-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; }
    .official-report .report-table th {
        background: #f8f9fa;
        border: 1px solid #333;
        padding: 10px 8px;
        font-weight: 800;
        font-size: 0.72rem;
        text-transform: uppercase;
        text-align: center;
    }
    .official-report .category-row { background: #fdecea; font-weight: 800; text-transform: uppercase; font-size: 0.82rem; }
    .official-report .category-row td { padding: 8px 12px; border: 1px solid #333; }
    .official-report .report-table td {
        border: 1px solid #333;
        padding: 9px 8px;
        font-size: 0.84rem;
        text-align: center;
        vertical-align: middle;
    }
    .official-report .report-table td.text-left { text-align: left; font-weight: 700; color: #1a1a1a; padding-left: 12px; }
    .official-report .report-table tfoot th {
        background: #fff;
        border: 1px solid #333;
        padding: 9px 8px;
        font-size: 0.84rem;
        text-align: right;
    }
    .official-report .report-table tfoot .grand-total th {
        background: #fdecea;
        font-size: 0.95rem;
        color: var(--report-accent);
    }
    .official-report .amount-accent { font-weight: 800; font-size: 1rem; color: var(--report-accent); white-space: nowrap; }
    .official-report .packaging-badge { color: var(--report-accent); font-weight: 700; font-size: 0.8rem; }
    .official-report .filter-tile .form-group { margin-bottom: 0.75rem; }

    @media (max-width: 767.98px) {
        .official-report .report-sheet { padding: 16px; }
        .official-report .report-stats-grid { grid-template-columns: 1fr; }
        .official-report .official-stamp { display: none; }
    }

    @media print {
        @page { size: portrait; margin: 0.5cm; }
        .app-header, .app-sidebar, .d-print-none, .breadcrumb, .app-title, .support-fab, #supportQuickModal { display: none !important; }
        .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .official-report .report-sheet { padding: 0; border-radius: 0; box-shadow: none !important; }
        .official-report .report-table { border: 2px solid #000; font-size: 0.72rem; }
        .official-report .report-table th,
        .official-report .report-table td,
        .official-report .report-table tfoot th { border: 1.5px solid #000; padding: 4px !important; }
        .official-report .category-row td { padding: 4px !important; }
        .official-report .report-header-center h1 { font-size: 1.6rem; }
        .official-report .report-stats-grid { gap: 15px; margin-bottom: 10px; }
        tbody tr { page-break-inside: avoid; }
    }
</style>
