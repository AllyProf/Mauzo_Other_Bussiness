@php
    $totalNetCost = $totals['net_cost'] ?? 0;
    $totalExpectedRevenue = $totals['expected_revenue'] ?? 0;
    $totalExpectedProfit = $totals['expected_profit'] ?? 0;
    $hasDiscount = $receiving->items->contains(fn ($item) => (float) ($item->discount_amount ?? 0) > 0);
@endphp
<table class="{{ $tableClass ?? 'report-table mb-0' }}">
    <thead>
        <tr>
            <th style="width:36px;">#</th>
            <th class="text-left">{{ __('tables.columns.item_name') }}</th>
            <th style="width:100px;">{{ __('receivings.show.quantity') }}</th>
            <th style="width:110px;">{{ __('receivings.show.unit_cost') }}</th>
            <th style="width:140px;">{{ __('receivings.show.sell_price') }}</th>
            @if($hasDiscount)
            <th style="width:80px;">{{ __('receivings.show.discount') }}</th>
            @endif
            <th style="width:110px;">{{ __('receivings.show.line_cost') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receiving->items as $index => $item)
            @php
                $metrics = $lineMetrics[$item->id] ?? null;
                $netCost = $metrics['net_cost'] ?? max(0, ($item->quantity * $item->cost_price) - (float) ($item->discount_amount ?? 0));
                $discountAmount = $metrics['discount_amount'] ?? (float) ($item->discount_amount ?? 0);
                $packagingPrices = $metrics['packaging_prices'] ?? [];
                $singlePieceOnly = count($packagingPrices) === 1 && (($packagingPrices[0]['quantity_per_unit'] ?? 1) === 1);
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-left">
                    {{ $item->item->name }}
                    @if($item->item->sku)
                        <br><small class="text-muted font-weight-normal">SKU: {{ $item->item->sku }}</small>
                    @endif
                </td>
                <td>{{ $metrics['quantity_label'] ?? $item->quantity }}</td>
                <td>{{ money($item->cost_price) }}</td>
                <td>
                    @if(!empty($packagingPrices))
                        @if($singlePieceOnly)
                            {{ money($packagingPrices[0]['selling_price']) }}
                        @else
                            @foreach($packagingPrices as $pkgPrice)
                                <div class="small {{ $loop->last ? '' : 'mb-1' }}">
                                    {{ $pkgPrice['name'] }}: {{ money($pkgPrice['selling_price']) }}
                                </div>
                            @endforeach
                        @endif
                    @else
                        {{ money($item->selling_price) }}
                    @endif
                </td>
                @if($hasDiscount)
                <td>
                    @if($discountAmount > 0)
                        {{ money($discountAmount) }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                @endif
                <td class="amount-accent">{{ money($netCost) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="grand-total">
            <th colspan="{{ $hasDiscount ? 6 : 5 }}" class="text-left" style="padding-left:12px;">{{ __('receivings.show.totals') }}</th>
            <th class="amount-accent">{{ money($totalNetCost) }}</th>
        </tr>
    </tfoot>
</table>
