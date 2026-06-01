@extends('layouts.app')

@section('title', 'Stock Loss Details')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-minus-circle"></i> {{ $stockLoss->reference_no }}</h1>
    <p>Stock loss / write-off record</p>
  </div>
  <a href="{{ route('stock-losses.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  @if(!$stockLoss->isCancelled())
    @canany(['cancel_stock_loss', 'record_stock_loss'])
    <form action="{{ route('stock-losses.cancel', $stockLoss) }}" method="POST" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-warning" onclick="confirmAction(event, 'Cancel this record?', 'Stock will be restored to inventory.')">
        <i class="fa fa-undo"></i> Cancel & Restore Stock
      </button>
    </form>
    @endcanany
  @endif
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="row mb-4">
        <div class="col-md-6">
          <p class="mb-1"><strong>Date:</strong> {{ $stockLoss->loss_date->format('M d, Y') }}</p>
          <p class="mb-1"><strong>Reason:</strong> {{ $stockLoss->reasonLabel() }}</p>
          <p class="mb-1"><strong>Recorded by:</strong> {{ $stockLoss->user->name ?? 'N/A' }}</p>
        </div>
        <div class="col-md-6 text-md-right">
          <p class="mb-1"><strong>Reference:</strong> {{ $stockLoss->reference_no }}</p>
          <p class="mb-1">
            <strong>Status:</strong>
            @if($stockLoss->isCancelled())
              <span class="badge badge-secondary">Cancelled</span>
            @else
              <span class="badge badge-danger">Recorded</span>
            @endif
          </p>
          <p class="mb-1"><strong>Total qty lost:</strong> {{ number_format($stockLoss->total_quantity, 2) }}</p>
          <p class="mb-0"><strong>Total cost value:</strong> <span class="text-danger">{{ money($stockLoss->total_cost_value) }}</span></p>
        </div>
      </div>

      @if($stockLoss->notes)
      <div class="alert alert-light border mb-4">
        <strong>Notes:</strong> {{ $stockLoss->notes }}
      </div>
      @endif

      <div class="table-responsive">
        <table class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Item</th>
              <th>Category</th>
              <th>SKU</th>
              <th class="text-right">Qty Lost</th>
              <th class="text-right">Unit Cost</th>
              <th class="text-right">Cost Value</th>
              <th>Line Notes</th>
            </tr>
          </thead>
          <tbody>
            @foreach($stockLoss->items as $line)
              <tr>
                <td>{{ $line->item->name ?? 'N/A' }}</td>
                <td>{{ $line->item->category->name ?? '—' }}</td>
                <td>{{ $line->item->sku ?? '—' }}</td>
                <td class="text-right">{{ number_format($line->quantity, 2) }}</td>
                <td class="text-right">{{ money($line->unit_cost) }}</td>
                <td class="text-right text-danger font-weight-bold">{{ money($line->cost_value) }}</td>
                <td>{{ $line->line_notes ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="text-right d-print-none mt-3">
        <button type="button" class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
      </div>
    </div>
  </div>
</div>
@endsection
