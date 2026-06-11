<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('stock.export.report_title') }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #2c3e50; font-size: 10px; margin: 0; padding: 0; }
        .report-sheet { width: 100%; margin: 0 auto; }
        .report-header-center { text-align: center; margin-bottom: 14px; }
        .report-header-center img { height: 44px; margin-bottom: 4px; }
        .report-header-center h1 { font-size: 18px; font-weight: bold; color: #940000; margin: 0; text-transform: uppercase; }
        .biz-contact-info { font-size: 8px; color: #555; margin-top: 4px; }
        .operations-title { color: #940000; font-weight: bold; font-size: 11px; margin-top: 4px; }
        .accent-divider { height: 2px; background: #940000; margin: 8px auto 0; border: none; width: 100%; }
        .report-sub-meta { width: 100%; font-size: 8px; color: #777; margin-bottom: 8px; }
        .report-sub-meta table { width: 100%; border-collapse: collapse; }
        .report-sub-meta td { width: 33.33%; vertical-align: top; }
        .report-sub-meta td:last-child { text-align: right; }
        .title-area { text-align: center; margin: 10px 0 12px; }
        .main-report-title { font-size: 13px; font-weight: bold; text-transform: uppercase; border-bottom: 2px solid #555; display: inline-block; padding-bottom: 2px; }
        .report-stats-grid { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .report-stats-grid td { width: 50%; vertical-align: top; padding: 0 10px 0 0; }
        .stats-card-title { font-size: 9px; font-weight: bold; color: #940000; text-transform: uppercase; border-bottom: 2px solid #940000; padding-bottom: 3px; margin-bottom: 5px; }
        .stats-section-title { width: 100%; text-align: center; margin: 0 0 8px; }
        .stats-section-title .stats-card-title { display: block; width: 100%; text-align: center; margin-bottom: 8px; }
        .stats-row { margin-bottom: 2px; font-size: 9px; }
        .stats-row strong { display: inline-block; width: 52%; }
        .table-wrap { width: 100%; margin: 0 auto; }
        .report-table { width: 100%; border-collapse: collapse; border: 1.5px solid #333; table-layout: fixed; margin: 0 auto; }
        .report-table th { background: #f8f9fa; border: 1px solid #333; padding: 5px 3px; font-weight: bold; font-size: 7px; text-transform: uppercase; text-align: center; vertical-align: middle; line-height: 1.25; word-wrap: break-word; }
        .report-table th.text-left { text-align: left; padding-left: 6px; }
        .category-row td { background: #fdecea; font-weight: bold; text-transform: uppercase; font-size: 8px; padding: 4px 6px; border: 1px solid #333; text-align: center; }
        .subtotal-row td { background: #f0f0f0; font-weight: bold; font-size: 7px; padding: 4px 3px; border: 1px solid #333; text-align: center; vertical-align: middle; }
        .subtotal-row td.text-left { text-align: left; padding-left: 6px; }
        .report-table td { border: 1px solid #333; padding: 4px 3px; font-size: 8px; text-align: center; vertical-align: middle; line-height: 1.3; word-wrap: break-word; overflow-wrap: break-word; }
        .report-table td.text-left { text-align: left; font-weight: bold; padding-left: 6px; font-size: 8px; }
        .report-table tfoot th { background: #fdecea; border: 1px solid #333; padding: 5px 3px; font-size: 8px; text-align: center; vertical-align: middle; }
        .report-table tfoot .grand-total th { color: #940000; font-weight: bold; background: #fdecea; }
        .report-table tfoot .grand-total th.text-left { text-align: left; padding-left: 6px; }
        .amount-accent { font-weight: bold; color: #940000; font-size: 7px; line-height: 1.2; }
        .amount-profit { font-weight: bold; color: #28a745; font-size: 7px; line-height: 1.2; }
        .packaging-badge { color: #940000; font-weight: bold; font-size: 7px; }
        .low-stock { background: #fff3cd; }
        .text-muted { color: #777; font-weight: normal; font-size: 7px; }
        .footer-note { margin-top: 10px; font-size: 7px; color: #666; border-top: 1px solid #ddd; padding-top: 6px; text-align: center; }
        .currency-note { font-size: 7px; color: #777; text-align: center; margin-bottom: 6px; }
    </style>
</head>
<body>
@php
    $logoDataUri = $business->invoiceLogoDataUri();
    $grouped = $groupedByCategory ?? $stockItems->groupBy('category')->sortKeys();
    $colspan = $canViewValue ? 11 : 9;
    $refCode = 'STK-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $business->name), 0, 3)).'-'.$generatedAt->format('Ymd');
    $pdfMoney = fn ($amount) => number_format((float) $amount, 0, '.', ',');
@endphp

<div class="report-sheet">
    <div class="report-header-center">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="{{ $business->name }}">
        @endif
        <h1>{{ $business->name }}</h1>
        <div class="biz-contact-info">
            @if($business->address){{ $business->address }}@endif
            @if($business->phone) | {{ $business->phone }}@endif
            @if($business->email) | {{ $business->email }}@endif
        </div>
        @if($activeBranchName)
            <div class="operations-title">{{ strtoupper($activeBranchName) }} — {{ __('stock.export.report_title') }}</div>
        @else
            <div class="operations-title">{{ __('stock.export.report_title') }}</div>
        @endif
        <hr class="accent-divider">
    </div>

    <div class="report-sub-meta">
        <table>
            <tr>
                <td>{{ __('stock.export.prepared_by', ['name' => $generatedBy ?? Auth::user()->name]) }}</td>
                <td style="text-align:center;">{{ __('stock.export.date_label', ['date' => $generatedAt->format('d M Y H:i')]) }}</td>
                <td>Ref: {{ $refCode }}</td>
            </tr>
        </table>
    </div>

    <div class="title-area">
        <div class="main-report-title">{{ __('stock.export.inventory_snapshot') }}</div>
    </div>

    <table class="report-stats-grid">
        <tr>
            <td>
                <div class="stats-card-title">{{ __('stock.export.report_information') }}</div>
                <div class="stats-row"><strong>{{ __('stock.export.branch_label', ['branch' => '']) }}</strong> {{ $activeBranchName ?? __('stock.export.all_branches') }}</div>
                <div class="stats-row"><strong>{{ __('stock.export.low_stock_threshold') }}</strong> ≤ {{ $lowStockThreshold }}</div>
                <div class="stats-row"><strong>{{ __('price_list.currency') }}</strong> TZS</div>
            </td>
            <td>
                <div class="stats-card-title">{{ __('price_list.summary') }}</div>
                <div class="stats-row"><strong>{{ __('stock.stats.total_items') }}</strong> {{ $stats['total_items'] }}</div>
                <div class="stats-row"><strong>{{ __('stock.stats.low_stock_items') }}</strong> {{ $stats['low_stock'] }}</div>
                @if($canViewValue)
                <div class="stats-row"><strong>{{ __('stock.stats.expected_revenue') }}</strong> <span class="amount-accent">{{ money($totalExpectedRevenue ?? $totalValue) }}</span></div>
                <div class="stats-row"><strong>{{ __('stock.stats.expected_profit') }}</strong> <span class="amount-profit">{{ money($totalExpectedProfit ?? $totalMargin ?? 0) }}</span></div>
                @endif
            </td>
        </tr>
    </table>

    @if($grouped->isEmpty())
        <p style="text-align:center;">{{ __('stock.empty.general_title') }}</p>
    @else
        <div class="stats-section-title">
            <div class="stats-card-title">{{ __('stock.export.selling_and_stock') }}</div>
        </div>
        <div class="currency-note">{{ __('stock.export.amounts_in_tzs') }}</div>
        <div class="table-wrap">
            <table class="report-table">
                @if($canViewValue)
                <colgroup>
                    <col style="width:3%;">
                    <col style="width:16%;">
                    <col style="width:10%;">
                    <col style="width:6%;">
                    <col style="width:8%;">
                    <col style="width:6%;">
                    <col style="width:9%;">
                    <col style="width:9%;">
                    <col style="width:11%;">
                    <col style="width:11%;">
                    <col style="width:11%;">
                </colgroup>
                @else
                <colgroup>
                    <col style="width:4%;">
                    <col style="width:22%;">
                    <col style="width:12%;">
                    <col style="width:8%;">
                    <col style="width:10%;">
                    <col style="width:8%;">
                    <col style="width:12%;">
                    <col style="width:12%;">
                    <col style="width:12%;">
                </colgroup>
                @endif
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="text-left">{{ __('stock.export.col_item') }}</th>
                        <th>{{ __('stock.export.col_stock') }}</th>
                        <th>{{ __('stock.export.col_unit') }}</th>
                        <th>{{ __('stock.export.col_packaging') }}</th>
                        <th>{{ __('stock.export.col_pack_size') }}</th>
                        <th>{{ __('stock.export.col_sell_price') }}</th>
                        <th>{{ __('stock.export.col_price_per_piece') }}</th>
                        @if($canViewValue)
                        <th>{{ __('stock.export.col_expected_revenue') }}</th>
                        <th>{{ __('stock.export.col_expected_profit') }}</th>
                        @endif
                        <th>{{ __('stock.export.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $rowNum = 1; @endphp
                    @foreach($grouped as $categoryName => $categoryItems)
                        <tr class="category-row">
                            <td colspan="{{ $colspan }}">{{ $categoryName }}</td>
                        </tr>
                        @foreach($categoryItems as $item)
                            @php $packRows = $item['packaging_prices']; @endphp
                            @foreach($packRows as $packIndex => $pack)
                                @php
                                    $qtyPerUnit = max(1, (int) $pack['quantity_per_unit']);
                                    $pricePerPiece = $pack['selling_price'] / $qtyPerUnit;
                                    $rowClass = $item['is_low_stock'] ? 'low-stock' : '';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    @if($packIndex === 0)
                                        <td rowspan="{{ count($packRows) }}">{{ $rowNum++ }}</td>
                                        <td rowspan="{{ count($packRows) }}" class="text-left">
                                            {{ $item['name'] }}
                                            @if($item['sku'])
                                                <br><span class="text-muted">SKU: {{ $item['sku'] }}</span>
                                            @endif
                                        </td>
                                        <td rowspan="{{ count($packRows) }}">{{ $item['stock_display'] }}</td>
                                        <td rowspan="{{ count($packRows) }}">{{ $item['unit'] }}</td>
                                    @endif
                                    <td><span class="packaging-badge">{{ $pack['name'] }}</span></td>
                                    <td>{{ $qtyPerUnit > 1 ? $qtyPerUnit.' '.__('stock.card.pcs') : '1' }}</td>
                                    <td class="amount-accent">
                                        @if($pack['selling_price'] > 0)
                                            {{ $pdfMoney($pack['selling_price']) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pack['selling_price'] > 0)
                                            {{ $pdfMoney($pricePerPiece) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    @if($canViewValue)
                                        @if($packIndex === 0)
                                            <td rowspan="{{ count($packRows) }}" class="amount-accent">{{ $pdfMoney($item['expected_revenue']) }}</td>
                                            <td rowspan="{{ count($packRows) }}" class="amount-profit">
                                                {{ $item['expected_profit'] != 0 ? $pdfMoney($item['expected_profit']) : '—' }}
                                            </td>
                                            <td rowspan="{{ count($packRows) }}">{{ $item['is_low_stock'] ? __('stock.status.low_stock') : __('stock.status.in_stock') }}</td>
                                        @endif
                                    @else
                                        @if($packIndex === 0)
                                            <td rowspan="{{ count($packRows) }}">{{ $item['is_low_stock'] ? __('stock.status.low_stock') : __('stock.status.in_stock') }}</td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                        @if($canViewValue)
                        @php
                            $catRevenue = $categoryItems->sum('expected_revenue');
                            $catProfit = $categoryItems->sum('expected_profit');
                        @endphp
                        <tr class="subtotal-row">
                            <td colspan="8" class="text-left">{{ __('stock.export.category_subtotal', ['category' => $categoryName]) }}</td>
                            <td class="amount-accent">{{ $pdfMoney($catRevenue) }}</td>
                            <td class="amount-profit">{{ $pdfMoney($catProfit) }}</td>
                            <td></td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
                @if($canViewValue && ($totalExpectedRevenue ?? $totalValue ?? 0) > 0)
                <tfoot>
                    <tr class="grand-total">
                        <th colspan="8" class="text-left">{{ __('stock.export.grand_total') }}</th>
                        <th class="amount-accent">{{ $pdfMoney($totalExpectedRevenue ?? $totalValue) }}</th>
                        <th class="amount-profit">{{ $pdfMoney($totalExpectedProfit ?? $totalMargin ?? 0) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endif

    <div class="footer-note">
        {{ __('stock.export.footer_note') }}
    </div>
</div>
</body>
</html>
