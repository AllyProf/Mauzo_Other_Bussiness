@extends('layouts.app')

@section('title', 'Receiving Details')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-truck"></i> Receiving #{{ $receiving->reference_no }}</h1>
    <p>Details for stock-in transaction</p>
  </div>
  <a href="{{ route('receivings.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to History</a>
  @if(($receiving->status ?? 'completed') !== 'cancelled')
  <form action="{{ route('receivings.cancel', $receiving->id) }}" method="POST" style="display:inline-block;">
      @csrf
      <button type="submit" class="btn btn-danger" onclick="confirmAction(event, 'Cancel Receiving?', 'Stock added by this record will be removed from inventory.')"><i class="fa fa-times"></i> Cancel Receiving</button>
  </form>
  @endif
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="row mb-4">
        <div class="col-6">
          <h2 class="page-header"><i class="fa fa-file-text-o"></i> Stock-In Record</h2>
        </div>
        <div class="col-6">
          <h5 class="text-right">Date: {{ \Carbon\Carbon::parse($receiving->received_date)->format('M d, Y') }}</h5>
        </div>
      </div>
      <div class="row invoice-info">
        <div class="col-4">
          From Supplier:
          <address>
            <strong>{{ $receiving->supplier->name ?? 'N/A' }}</strong><br>
            {{ $receiving->supplier->address ?? '' }}<br>
            Phone: {{ $receiving->supplier->phone ?? '' }}<br>
            Email: {{ $receiving->supplier->email ?? '' }}
          </address>
        </div>
        <div class="col-4">
          Received By:
          <address>
            <strong>{{ $receiving->user->name }}</strong><br>
            {{ Auth::user()->business->name }}<br>
            Email: {{ $receiving->user->email }}
          </address>
        </div>
        <div class="col-4">
          <b>Reference:</b> {{ $receiving->reference_no }}<br>
          <b>Branch:</b> {{ $receiving->branch->name ?? '—' }}<br>
          <b>Status:</b>
          @if(($receiving->status ?? 'completed') === 'cancelled')
              <span class="badge badge-secondary">Cancelled</span>
          @else
              <span class="badge badge-success">Completed</span>
          @endif
          <br>
          <b>Notes:</b> {{ $receiving->notes ?? 'N/A' }}
        </div>
      </div>
      <div class="row mt-4">
        <div class="col-12 table-responsive">
          <table class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>SKU</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Discount</th>
                <th>Sell Price</th>
                <th>Total Cost</th>
                <th>Expected Revenue</th>
                <th>Expected Profit</th>
              </tr>
            </thead>
            <tbody>
              @php
                $totalNetCost = 0;
                $totalExpectedRevenue = 0;
                $totalExpectedProfit = 0;
              @endphp
              @foreach($receiving->items as $item)
                @php
                  $metrics = $lineMetrics[$item->id] ?? null;
                  $netCost = $metrics['net_cost'] ?? max(0, ($item->quantity * $item->cost_price) - (float) ($item->discount_amount ?? 0));
                  $expectedRevenue = $metrics['expected_revenue'] ?? ($item->quantity * $item->selling_price);
                  $expectedProfit = $metrics['expected_profit'] ?? ($expectedRevenue - $netCost);
                  $discountAmount = $metrics['discount_amount'] ?? (float) ($item->discount_amount ?? 0);
                  $totalNetCost += $netCost;
                  $totalExpectedRevenue += $expectedRevenue;
                  $totalExpectedProfit += $expectedProfit;
                @endphp
                <tr>
                  <td>{{ $item->item->name }}</td>
                  <td>{{ $item->item->sku }}</td>
                  <td>{{ $metrics['quantity_label'] ?? $item->quantity }}</td>
                  <td>TZS {{ number_format($item->cost_price, 2) }}<br><small class="text-muted">per {{ $metrics['receiving_unit'] ?? 'unit' }}</small></td>
                  <td>
                    @if($discountAmount > 0)
                      @if($item->discount_type === 'percent')
                        {{ number_format($item->discount_value, 0) }}% (TZS {{ number_format($discountAmount, 2) }})
                      @else
                        TZS {{ number_format($discountAmount, 2) }}
                      @endif
                    @else
                      —
                    @endif
                  </td>
                  <td>
                    @php
                      $packagingPrices = $metrics['packaging_prices'] ?? [];
                      $sellPerPiece = $metrics['sell_per_piece'] ?? (float) $item->selling_price;
                      $singlePieceOnly = count($packagingPrices) === 1
                        && (($packagingPrices[0]['quantity_per_unit'] ?? 1) === 1);
                    @endphp
                    @if(!empty($packagingPrices))
                      @if($singlePieceOnly)
                        <strong>TZS {{ number_format($packagingPrices[0]['selling_price'], 2) }}</strong>
                        <br><small class="text-muted">per {{ $packagingPrices[0]['name'] }}</small>
                      @else
                        @foreach($packagingPrices as $pkgPrice)
                          <div class="small {{ $loop->last ? '' : 'mb-1' }}">
                            <strong>{{ $pkgPrice['name'] }}</strong>
                            @if($pkgPrice['quantity_per_unit'] > 1)
                              ({{ $pkgPrice['quantity_per_unit'] }} pcs):
                            @else
                              :
                            @endif
                            TZS {{ number_format($pkgPrice['selling_price'], 2) }}
                          </div>
                        @endforeach
                        @if($sellPerPiece > 0 && collect($packagingPrices)->contains(fn ($p) => ($p['quantity_per_unit'] ?? 1) > 1))
                          <small class="text-muted d-block mt-1">Revenue basis: TZS {{ number_format($sellPerPiece, 2) }} / piece</small>
                        @endif
                      @endif
                    @else
                      <strong>TZS {{ number_format($item->selling_price, 2) }}</strong>
                      <br><small class="text-muted">per piece</small>
                    @endif
                  </td>
                  <td>TZS {{ number_format($netCost, 2) }}</td>
                  <td>TZS {{ number_format($expectedRevenue, 2) }}</td>
                  <td class="{{ $expectedProfit >= 0 ? 'text-success' : 'text-danger' }}">TZS {{ number_format($expectedProfit, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th colspan="6" class="text-right">Totals:</th>
                <th>TZS {{ number_format($totalNetCost, 2) }}</th>
                <th>TZS {{ number_format($totalExpectedRevenue, 2) }}</th>
                <th class="{{ $totalExpectedProfit >= 0 ? 'text-success' : 'text-danger' }}">TZS {{ number_format($totalExpectedProfit, 2) }}</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="row d-print-none mt-2">
        <div class="col-12 text-right">
          <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print Receipt</button>
        </div>
      </div>
    </div>
  </div>
</div>

@include('receivings.partials.cancel-prompt-form')
@endsection

@section('scripts')
@include('receivings.partials.cancel-prompt-script')
@endsection
