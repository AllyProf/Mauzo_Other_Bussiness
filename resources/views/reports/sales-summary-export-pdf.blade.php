<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #000000; font-size: 10px; margin: 0; padding: 0; }
        .sheet { width: 100%; }
        .header { text-align: center; margin-bottom: 10px; }
        .header img { height: 40px; margin-bottom: 4px; }
        .header h1 { font-size: 16px; font-weight: bold; color: #940000; margin: 0; text-transform: uppercase; }
        .contact { font-size: 8px; color: #000000; margin-top: 3px; }
        .ops { color: #940000; font-weight: bold; font-size: 11px; margin-top: 4px; }
        .divider { height: 2px; background: #940000; margin: 8px 0 10px; border: none; }
        .meta { width: 100%; font-size: 8px; color: #000000; margin-bottom: 8px; }
        .meta td { vertical-align: top; }
        .meta td:last-child { text-align: right; }
        .title { text-align: center; margin: 4px 0 10px; font-size: 12px; font-weight: bold; text-transform: uppercase; border-bottom: 2px solid #555; display: inline-block; padding-bottom: 2px; }
        .title-wrap { text-align: center; }
        .kpi { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .kpi td { width: 25%; border: 1px solid #ddd; padding: 8px 6px; text-align: center; vertical-align: top; background: #fafafa; }
        .kpi .label { display: block; font-size: 7px; text-transform: uppercase; color: #000000; margin-bottom: 3px; }
        .kpi .value { display: block; font-size: 11px; font-weight: bold; color: #940000; }
        .kpi .value.green { color: #28a745; }
        .kpi .value.muted { color: #000000; font-size: 9px; }
        .section { margin: 12px 0 6px; font-size: 9px; font-weight: bold; color: #940000; text-transform: uppercase; border-bottom: 1.5px solid #940000; padding-bottom: 3px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .table th { background: #f8f9fa; border: 1px solid #333; padding: 5px 4px; font-size: 7px; text-transform: uppercase; text-align: center; }
        .table th.left, .table td.left { text-align: left; padding-left: 6px; }
        .table td { border: 1px solid #333; padding: 4px; font-size: 8px; text-align: center; }
        .bar-row { margin-bottom: 6px; }
        .bar-label { font-size: 8px; margin-bottom: 2px; }
        .bar-track { width: 100%; height: 10px; background: #eee; border: 1px solid #ccc; }
        .bar-fill { height: 10px; background: #940000; }
        .bar-fill.alt { background: #1565C0; }
        .bar-fill.green { background: #28a745; }
        .bar-fill.orange { background: #e67e22; }
        .comments { background: #fff8f7; border: 1px solid #f0d0cc; padding: 8px 10px; margin-top: 8px; }
        .comments li { margin: 0 0 4px 12px; font-size: 8px; line-height: 1.35; color: #000000; }
        .footer { margin-top: 12px; font-size: 7px; color: #000000; border-top: 1px solid #ddd; padding-top: 6px; text-align: center; }
        .note { font-size: 7px; color: #000000; text-align: center; margin-bottom: 6px; }
        .up { color: #28a745; font-weight: bold; }
        .down { color: #c0392b; font-weight: bold; }
        .alert-box { background: #fff5f5; border: 1px solid #f5c6cb; padding: 6px 8px; margin-bottom: 8px; font-size: 8px; }
    </style>
</head>
<body>
@php
    $logoDataUri = $business->invoiceLogoDataUri();
    $m = fn ($amount) => number_format((float) $amount, 0, '.', ',');
    $branches = $branchRows ?? collect();
    $maxBranch = max(1, (float) $branches->max('gross'));
    $gross = (float) ($stats['gross'] ?? 0);
    $collected = (float) ($stats['collected'] ?? 0);
    $collectPct = $gross > 0 ? min(100, round(($collected / $gross) * 100)) : 0;
    $payments = $paymentBreakdown ?? collect();
    $products = $topProducts ?? collect();
    $expenseData = $expenses ?? ['staff_total' => 0, 'owner_total' => 0, 'total' => 0, 'staff_rows' => collect(), 'owner_rows' => collect()];
    $compare = $comparison ?? null;
    $lowStockRows = $lowStock ?? collect();
    $shortageRows = $shortages ?? collect();
    $receivedData = $receivedItems ?? ['receipts' => 0, 'lines' => 0, 'total_amount' => 0, 'rows' => collect()];
    $receivedRows = collect($receivedData['rows'] ?? []);
    $stockVal = $stockValue ?? ['item_count' => 0, 'pieces' => 0, 'selling_value' => 0, 'cost_value' => 0];
    $fmtPct = function ($pct) {
        if ($pct === null) {
            return '—';
        }
        $sign = $pct > 0 ? '+' : '';
        return $sign.number_format((float) $pct, 1).'%';
    };
    $pctClass = function ($pct) {
        if ($pct === null) return '';
        return $pct >= 0 ? 'up' : 'down';
    };
@endphp

<div class="sheet">
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="{{ $business->name }}">
        @endif
        <h1>{{ $business->name }}</h1>
        <div class="contact">
            @if($business->address){{ $business->address }}@endif
            @if($business->phone) | {{ $business->phone }}@endif
            @if($business->email) | {{ $business->email }}@endif
        </div>
        <div class="ops">{{ strtoupper($reportTitle) }}</div>
        <hr class="divider">
    </div>

    <table class="meta">
        <tr>
            <td>Prepared by: {{ $generatedBy }}</td>
            <td style="text-align:center;">{{ $generatedAt->format('d M Y H:i') }}</td>
            <td>Ref: {{ $refCode }}</td>
        </tr>
    </table>

    <div class="title-wrap"><div class="title">{{ $periodLabel }}</div></div>

    <table class="kpi">
        <tr>
            <td>
                <span class="label">Orders</span>
                <span class="value">{{ number_format($stats['orders'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Gross sales</span>
                <span class="value">{{ $m($stats['gross'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Collected</span>
                <span class="value">{{ $m($stats['collected'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Outstanding</span>
                <span class="value">{{ $m($stats['outstanding'] ?? 0) }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Total profit</span>
                <span class="value green">{{ $m($stats['profit'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Opening circulation</span>
                <span class="value">{{ $m($stats['opening_circulation'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Returned / refill</span>
                <span class="value">{{ $m($stats['circulation_returned'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Closing circulation</span>
                <span class="value">{{ $m($stats['closing_circulation'] ?? 0) }}</span>
            </td>
        </tr>
        @if(is_array($compare))
        <tr>
            <td>
                <span class="label">Gross {{ $compare['label'] ?? '' }}</span>
                <span class="value muted {{ $pctClass($compare['gross_change_pct'] ?? null) }}">{{ $fmtPct($compare['gross_change_pct'] ?? null) }}</span>
            </td>
            <td>
                <span class="label">Orders change</span>
                <span class="value muted {{ $pctClass($compare['orders_change_pct'] ?? null) }}">{{ $fmtPct($compare['orders_change_pct'] ?? null) }}</span>
            </td>
            <td>
                <span class="label">Collected change</span>
                <span class="value muted {{ $pctClass($compare['collected_change_pct'] ?? null) }}">{{ $fmtPct($compare['collected_change_pct'] ?? null) }}</span>
            </td>
            <td>
                <span class="label">Prev period sales</span>
                <span class="value muted">{{ $m($compare['previous_gross'] ?? 0) }}</span>
            </td>
        </tr>
        @endif
        <tr>
            <td>
                <span class="label">Stock items (on hand)</span>
                <span class="value muted">{{ number_format((int) ($stockVal['item_count'] ?? 0)) }}</span>
            </td>
            <td>
                <span class="label">Stock value (selling)</span>
                <span class="value">{{ $m($stockVal['selling_value'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Stock value (cost)</span>
                <span class="value muted">{{ $m($stockVal['cost_value'] ?? 0) }}</span>
            </td>
            <td>
                <span class="label">Received this period</span>
                <span class="value muted">{{ number_format((int) ($receivedData['lines'] ?? 0)) }} lines</span>
            </td>
        </tr>
    </table>

    <div class="section">Collection vs sales</div>
    <div class="bar-row">
        <div class="bar-label">Collected {{ $collectPct }}% of gross sales (TZS {{ $m($collected) }} / {{ $m($gross) }})</div>
        <div class="bar-track"><div class="bar-fill green" style="width: {{ $collectPct }}%;"></div></div>
    </div>
    @if(($stats['outstanding'] ?? 0) > 0)
    <div class="bar-row">
        <div class="bar-label">Outstanding debt TZS {{ $m($stats['outstanding']) }}</div>
        <div class="bar-track"><div class="bar-fill alt" style="width: {{ $gross > 0 ? min(100, round((((float)$stats['outstanding'])/$gross)*100)) : 0 }}%;"></div></div>
    </div>
    @endif

    @if($payments->isNotEmpty())
        <div class="section">Payment breakdown</div>
        @foreach($payments as $pay)
            <div class="bar-row">
                <div class="bar-label">{{ $pay['label'] }} — TZS {{ $m($pay['amount']) }} ({{ $pay['pct'] }}%)</div>
                <div class="bar-track"><div class="bar-fill {{ $loop->index % 2 === 0 ? '' : 'alt' }}" style="width: {{ min(100, (int)$pay['pct']) }}%;"></div></div>
            </div>
        @endforeach
        <table class="table">
            <thead>
                <tr>
                    <th class="left">Method</th>
                    <th>Amount</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payments as $pay)
                <tr>
                    <td class="left">{{ $pay['label'] }}</td>
                    <td>{{ $m($pay['amount']) }}</td>
                    <td>{{ $pay['pct'] }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($branches->count() > 1)
        <div class="section">Branch comparison (manager digest)</div>
        @foreach($branches as $branch)
            @php $pct = (int) round((((float) $branch['gross']) / $maxBranch) * 100); @endphp
            <div class="bar-row">
                <div class="bar-label">{{ $branch['name'] }} — sales TZS {{ $m($branch['gross']) }} · collected TZS {{ $m($branch['collected']) }} · orders {{ $branch['orders'] }}</div>
                <div class="bar-track"><div class="bar-fill" style="width: {{ $pct }}%;"></div></div>
            </div>
        @endforeach

        <table class="table">
            <thead>
                <tr>
                    <th class="left">Branch</th>
                    <th>Orders</th>
                    <th>Gross</th>
                    <th>Collected</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
            @foreach($branches as $branch)
                <tr>
                    <td class="left">{{ $branch['name'] }}</td>
                    <td>{{ $branch['orders'] }}</td>
                    <td>{{ $m($branch['gross']) }}</td>
                    <td>{{ $m($branch['collected']) }}</td>
                    <td>{{ $gross > 0 ? round((((float)$branch['gross'])/$gross)*100) : 0 }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($products->isNotEmpty())
        <div class="section">Top products sold</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th class="left">Product / service</th>
                    <th>Qty</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            @foreach($products as $i => $product)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="left">{{ $product['name'] }}</td>
                    <td>{{ rtrim(rtrim(number_format((float)$product['qty'], 2), '0'), '.') }}</td>
                    <td>{{ $m($product['revenue']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(($expenseData['total'] ?? 0) > 0)
        <div class="section">Expenses</div>
        <table class="kpi">
            <tr>
                <td>
                    <span class="label">Staff expenses</span>
                    <span class="value">{{ $m($expenseData['staff_total'] ?? 0) }}</span>
                </td>
                <td>
                    <span class="label">Owner expenses</span>
                    <span class="value">{{ $m($expenseData['owner_total'] ?? 0) }}</span>
                </td>
                <td>
                    <span class="label">Total expenses</span>
                    <span class="value">{{ $m($expenseData['total'] ?? 0) }}</span>
                </td>
                <td>
                    <span class="label">Net after expenses*</span>
                    <span class="value green">{{ $m(max(0, (float)($stats['profit'] ?? 0) - (float)($expenseData['total'] ?? 0))) }}</span>
                </td>
            </tr>
        </table>
        <div class="note">* Indicative only — uses reported profit minus period expenses.</div>
        @php
            $expenseRows = collect($expenseData['staff_rows'] ?? [])->concat(collect($expenseData['owner_rows'] ?? []))->take(12);
        @endphp
        @if($expenseRows->isNotEmpty())
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="left">By</th>
                    <th class="left">Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
            @foreach($expenseRows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td class="left">{{ $row['by'] }}</td>
                    <td class="left">{{ $row['description'] }}</td>
                    <td>{{ $m($row['amount']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    @endif

    @if($receivedRows->isNotEmpty())
        <div class="section">Items received ({{ $receivedData['receipts'] ?? 0 }} receipts · TZS {{ $m($receivedData['total_amount'] ?? 0) }})</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:12%;">Date</th>
                    <th class="left" style="width:14%;">Ref</th>
                    <th class="left" style="width:18%;">Supplier</th>
                    <th class="left" style="width:30%;">Item</th>
                    <th style="width:14%;">Qty</th>
                    <th style="width:12%;">Cost</th>
                </tr>
            </thead>
            <tbody>
            @foreach($receivedRows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td class="left">{{ $row['reference'] }}</td>
                    <td class="left">{{ $row['supplier'] }}</td>
                    <td class="left">{{ $row['item'] }}</td>
                    <td>{{ $row['qty_label'] }}</td>
                    <td>{{ $m($row['amount'] ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if(($receivedData['lines'] ?? 0) > $receivedRows->count())
            <div class="note">Showing {{ $receivedRows->count() }} of {{ $receivedData['lines'] }} received lines.</div>
        @endif
    @endif

    @if($lowStockRows->isNotEmpty() || $shortageRows->isNotEmpty())
        <div class="section">Low stock &amp; shortages</div>
        @if($lowStockRows->isNotEmpty())
            <div class="alert-box"><strong>Low stock:</strong>
                @foreach($lowStockRows as $row)
                    {{ $row['name'] }} ({{ rtrim(rtrim(number_format((float)$row['stock'], 2), '0'), '.') }})@if(!$loop->last), @endif
                @endforeach
            </div>
        @endif
        @if($shortageRows->isNotEmpty())
            <table class="table">
                <thead>
                    <tr>
                        <th class="left">Shortage item</th>
                        <th>Qty short</th>
                        <th class="left">Staff</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($shortageRows as $row)
                    <tr>
                        <td class="left">{{ $row['item'] }}</td>
                        <td>{{ rtrim(rtrim(number_format((float)$row['qty'], 2), '0'), '.') }}</td>
                        <td class="left">{{ $row['staff'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if(!empty($comments) && count($comments))
        <div class="section">Sales comments</div>
        <div class="comments">
            <ul>
                @foreach($comments as $comment)
                    <li>{{ $comment }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="section">Top sales (summary)</div>
    <div class="note">Amounts in TZS · showing up to 12 invoices</div>
    <table class="table">
        <thead>
            <tr>
                <th style="width:6%;">#</th>
                <th class="left" style="width:18%;">Invoice</th>
                <th style="width:14%;">Date</th>
                <th class="left" style="width:22%;">Customer</th>
                <th class="left" style="width:16%;">Cashier</th>
                <th style="width:12%;">Gross</th>
                <th style="width:12%;">Paid</th>
            </tr>
        </thead>
        <tbody>
        @forelse(($salesRows ?? collect())->take(12) as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="left">{{ $row['reference'] }}</td>
                <td>{{ $row['date'] }}</td>
                <td class="left">{{ $row['customer'] }}</td>
                <td class="left">{{ $row['cashier'] }}</td>
                <td>{{ $m($row['gross']) }}</td>
                <td>{{ $m($row['paid']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No sales in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">Generated by Mauzo Link · {{ $generatedAt->format('d M Y H:i') }}</div>
</div>
</body>
</html>
