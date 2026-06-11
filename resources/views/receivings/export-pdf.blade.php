<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('receivings.export.report_title') }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #2c3e50; font-size: 10px; margin: 0; }
        .report-header-center { text-align: center; margin-bottom: 12px; }
        .report-header-center img { height: 44px; margin-bottom: 4px; }
        .report-header-center h1 { font-size: 18px; font-weight: bold; color: #940000; margin: 0; text-transform: uppercase; }
        .biz-contact-info { font-size: 8px; color: #555; margin-top: 4px; }
        .operations-title { color: #940000; font-weight: bold; font-size: 11px; margin-top: 4px; }
        .accent-divider { height: 2px; background: #940000; margin: 8px 0 0; border: none; }
        .report-sub-meta table { width: 100%; font-size: 8px; color: #777; margin-bottom: 10px; border-collapse: collapse; }
        .report-sub-meta td { width: 33.33%; vertical-align: top; }
        .report-sub-meta td:nth-child(2) { text-align: center; }
        .report-sub-meta td:last-child { text-align: right; }
        .stats-grid { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .stats-grid td { width: 25%; vertical-align: top; padding: 6px 8px; background: #f8f5f5; border: 1px solid #ddd; text-align: center; }
        .stats-grid .label { font-size: 7px; text-transform: uppercase; color: #777; display: block; margin-bottom: 2px; }
        .stats-grid .value { font-size: 11px; font-weight: bold; color: #940000; }
        .section-title { text-align: center; font-size: 10px; font-weight: bold; color: #940000; text-transform: uppercase; border-bottom: 2px solid #940000; padding-bottom: 4px; margin: 8px 0; }
        .report-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; table-layout: fixed; }
        .report-table th { background: #f8f9fa; border: 1px solid #333; padding: 5px 3px; font-size: 7px; text-transform: uppercase; text-align: center; vertical-align: middle; }
        .report-table td { border: 1px solid #333; padding: 4px 3px; font-size: 8px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .report-table td.text-left { text-align: left; padding-left: 5px; font-weight: bold; }
        .report-table tfoot th { background: #fdecea; border: 1px solid #333; padding: 5px 3px; font-size: 8px; text-align: center; color: #940000; font-weight: bold; }
        .cancelled-row { background: #fff3cd; }
        .amount { font-weight: bold; color: #940000; font-size: 7px; }
        .footer-note { margin-top: 10px; font-size: 7px; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
@php
    $logoDataUri = $business->invoiceLogoDataUri();
    $refCode = 'RCV-RPT-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $business->name), 0, 3)).'-'.$generatedAt->format('Ymd');
    $pdfMoney = fn ($amount) => number_format((float) $amount, 0, '.', ',');
@endphp

<div class="report-header-center">
    @if($logoDataUri)
        <img src="{{ $logoDataUri }}" alt="{{ $business->name }}">
    @endif
    <h1>{{ $business->name }}</h1>
    <div class="biz-contact-info">
        @if($business->address){{ $business->address }}@endif
        @if($business->phone) | {{ $business->phone }}@endif
    </div>
    <div class="operations-title">{{ __('receivings.export.report_title') }}</div>
    <hr class="accent-divider">
</div>

<div class="report-sub-meta">
    <table>
        <tr>
            <td>{{ __('receivings.export.prepared_by', ['name' => $generatedBy ?? '']) }}</td>
            <td>{{ __('receivings.export.period_label', ['period' => $dateFilter['label']]) }}</td>
            <td>Ref: {{ $refCode }}</td>
        </tr>
    </table>
</div>

<table class="stats-grid">
    <tr>
        <td><span class="label">{{ __('receivings.export.total_records') }}</span><span class="value">{{ $stats['total_records'] }}</span></td>
        <td><span class="label">{{ __('receivings.export.completed') }}</span><span class="value">{{ $stats['completed'] }}</span></td>
        <td><span class="label">{{ __('receivings.export.cancelled') }}</span><span class="value">{{ $stats['cancelled'] }}</span></td>
        <td><span class="label">{{ __('receivings.export.total_amount') }}</span><span class="value">{{ $pdfMoney($stats['total_amount']) }}</span></td>
    </tr>
</table>

<div class="section-title">{{ __('receivings.export.records_list') }}</div>

@if($receivings->isEmpty())
    <p style="text-align:center;">{{ __('receivings.export.empty') }}</p>
@else
    <table class="report-table">
        <colgroup>
            <col style="width:4%;">
            <col style="width:14%;">
            <col style="width:10%;">
            <col style="width:12%;">
            <col style="width:16%;">
            <col style="width:14%;">
            <col style="width:8%;">
            <col style="width:12%;">
            <col style="width:10%;">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('tables.columns.ref_no') }}</th>
                <th>{{ __('tables.columns.date') }}</th>
                <th>{{ __('tables.columns.branch') }}</th>
                <th>{{ __('tables.columns.supplier') }}</th>
                <th>{{ __('tables.columns.received_by') }}</th>
                <th>{{ __('receivings.export.items_count') }}</th>
                <th>{{ __('tables.columns.total_amount') }}</th>
                <th>{{ __('tables.columns.status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receivings as $index => $receiving)
                @php $isCancelled = ($receiving->status ?? 'completed') === 'cancelled'; @endphp
                <tr class="{{ $isCancelled ? 'cancelled-row' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td class="text-left">{{ $receiving->reference_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($receiving->received_date)->format('d M Y') }}</td>
                    <td>{{ $receiving->branch->name ?? '—' }}</td>
                    <td>{{ $receiving->supplier->name ?? '—' }}</td>
                    <td>{{ $receiving->user->name ?? '—' }}</td>
                    <td>{{ $receiving->items->count() }}</td>
                    <td class="amount">{{ $pdfMoney($receiving->total_amount) }}</td>
                    <td>{{ $isCancelled ? __('tables.status.cancelled') : __('tables.status.completed') }}</td>
                </tr>
            @endforeach
        </tbody>
        @if($stats['total_amount'] > 0)
        <tfoot>
            <tr>
                <th colspan="7" style="text-align:left;padding-left:6px;">{{ __('receivings.export.grand_total') }} ({{ __('receivings.export.completed') }})</th>
                <th class="amount">{{ $pdfMoney($stats['total_amount']) }}</th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endif

<div class="footer-note">{{ __('receivings.export.footer_note') }}</div>
</body>
</html>
