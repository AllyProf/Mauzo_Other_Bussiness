<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('receivings.show.document_title') }} #{{ $receiving->reference_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #2c3e50; font-size: 10px; margin: 0; }
        .report-header-center { text-align: center; margin-bottom: 12px; }
        .report-header-center img { height: 44px; margin-bottom: 4px; }
        .report-header-center h1 { font-size: 18px; font-weight: bold; color: #940000; margin: 0; text-transform: uppercase; }
        .biz-contact-info { font-size: 8px; color: #555; margin-top: 4px; }
        .operations-title { color: #940000; font-weight: bold; font-size: 11px; margin-top: 4px; }
        .accent-divider { height: 2px; background: #940000; margin: 8px 0 0; border: none; }
        .report-stats-grid { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .report-stats-grid td { width: 50%; vertical-align: top; padding: 0 10px 0 0; }
        .stats-row { margin-bottom: 3px; font-size: 9px; }
        .stats-row strong { display: inline-block; min-width: 38%; }
        .title-area { text-align: right; margin: 0 0 10px; padding-right: 8%; }
        .official-stamp { display: inline-block; border: 3px solid #28a745; color: #28a745; padding: 3px 10px; font-weight: bold; font-size: 10px; transform: rotate(-8deg); border-radius: 6px; }
        .official-stamp.stamp-cancelled { border-color: #c0392b; color: #c0392b; }
        .stats-card-title { font-size: 9px; font-weight: bold; color: #940000; text-transform: uppercase; border-bottom: 2px solid #940000; padding-bottom: 3px; margin-bottom: 6px; }
        .report-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; table-layout: fixed; }
        .report-table th { background: #f8f9fa; border: 1px solid #333; padding: 5px 3px; font-size: 7px; text-transform: uppercase; text-align: center; vertical-align: middle; }
        .report-table td { border: 1px solid #333; padding: 4px 3px; font-size: 8px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .report-table td.text-left { text-align: left; padding-left: 6px; font-weight: bold; }
        .report-table tfoot th { background: #fdecea; border: 1px solid #333; padding: 5px 3px; font-size: 8px; text-align: center; color: #940000; font-weight: bold; }
        .amount-accent { font-weight: bold; color: #940000; }
        .text-muted { color: #777; font-weight: normal; font-size: 7px; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>
@php
    $logoDataUri = $business->invoiceLogoDataUri();
    $stampLabel = $isCancelled ? __('receivings.show.stamp_cancelled') : __('receivings.show.stamp_completed');
@endphp

<div class="report-header-center">
    @if($logoDataUri)
        <img src="{{ $logoDataUri }}" alt="">
    @endif
    <h1>{{ $business->name }}</h1>
    @if($business->phone)
    <div class="biz-contact-info">{{ $business->phone }}</div>
    @endif
    <div class="operations-title">{{ strtoupper($receiving->branch->name ?? $business->name) }} — {{ __('receivings.show.document_title') }}</div>
    <hr class="accent-divider">
</div>

<table class="report-stats-grid">
    <tr>
        <td>
            <div class="stats-row"><strong>{{ __('receivings.show.reference') }}:</strong> {{ $receiving->reference_no }}</div>
            <div class="stats-row"><strong>{{ __('receivings.show.date') }}:</strong> {{ \Carbon\Carbon::parse($receiving->received_date)->format('d M Y') }}</div>
            <div class="stats-row"><strong>{{ __('tables.columns.supplier') }}:</strong> {{ $receiving->supplier->name ?? '—' }}</div>
        </td>
        <td>
            <div class="stats-row"><strong>{{ __('receivings.show.received_by') }}:</strong> {{ $receiving->user->name }}</div>
            <div class="stats-row"><strong>{{ __('receivings.show.status') }}:</strong> {{ $isCancelled ? __('tables.status.cancelled') : __('tables.status.completed') }}</div>
            <div class="stats-row"><strong>{{ __('receivings.show.expected_revenue') }}:</strong> {{ money($totals['expected_revenue']) }}</div>
            <div class="stats-row"><strong>{{ __('receivings.show.expected_profit') }}:</strong> {{ money($totals['expected_profit']) }}</div>
        </td>
    </tr>
</table>

<div class="title-area">
    <div class="official-stamp {{ $isCancelled ? 'stamp-cancelled' : '' }}">{{ $stampLabel }}</div>
</div>

<div class="stats-card-title">{{ __('receivings.show.items_received') }}</div>
@include('receivings.partials.document-items-table', ['tableClass' => 'report-table mb-0'])

@if($receiving->notes)
<p style="font-size:8px;color:#666;margin-top:10px;"><strong>{{ __('receivings.show.notes') }}:</strong> {{ $receiving->notes }}</p>
@endif

</body>
</html>
