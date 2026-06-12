@extends('layouts.app')

@section('title', $stockAdjustment->reference_no)

@section('content')
<div class="app-title">
  <div>
    <h1 class="text-danger"><i class="fa fa-wrench"></i> {{ $stockAdjustment->reference_no }}</h1>
    <p>{{ __('stock_adjustments.show.subtitle') }}</p>
  </div>
  <div>
    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    @if(!$stockAdjustment->isCancelled())
      @can('adjust_stock')
      <form action="{{ route('stock-adjustments.cancel', $stockAdjustment) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-danger" onclick="confirmAction(event, @json(__('stock_adjustments.cancel_confirm_title')), @json(__('stock_adjustments.cancel_confirm_text')))">
          <i class="fa fa-undo"></i> {{ __('stock_adjustments.cancel_restore') }}
        </button>
      </form>
      @endcan
    @endif
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile border-danger" style="border-width: 2px;">
      <div class="row mb-4">
        <div class="col-md-6">
          <p class="mb-1"><strong>{{ __('stock_adjustments.show.date') }}:</strong> {{ $stockAdjustment->adjustment_date->format('d M Y') }}</p>
          <p class="mb-1"><strong>{{ __('stock_adjustments.show.reason') }}:</strong> {{ $stockAdjustment->reasonLabel() }}</p>
          <p class="mb-1"><strong>{{ __('stock_adjustments.show.recorded_by') }}:</strong> {{ $stockAdjustment->user->name ?? '—' }}</p>
          @if($stockAdjustment->branch)
          <p class="mb-0"><strong>{{ __('stock_adjustments.show.branch') }}:</strong> {{ $stockAdjustment->branch->name }}</p>
          @endif
        </div>
        <div class="col-md-6 text-md-right">
          <p class="mb-1"><strong>{{ __('stock_adjustments.show.reference') }}:</strong> {{ $stockAdjustment->reference_no }}</p>
          <p class="mb-1">
            <strong>{{ __('stock_adjustments.show.status') }}:</strong>
            @if($stockAdjustment->isCancelled())
              <span class="badge badge-secondary">{{ __('stock_adjustments.status.cancelled') }}</span>
            @else
              <span class="badge badge-danger">{{ __('stock_adjustments.status.completed') }}</span>
            @endif
          </p>
          @php $net = (float) $stockAdjustment->net_adjustment; @endphp
          <p class="mb-0"><strong>{{ __('stock_adjustments.show.net_change') }}:</strong>
            <span class="{{ $net >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">{{ $net > 0 ? '+' : '' }}{{ number_format($net, 0) }}</span>
          </p>
        </div>
      </div>

      @if($stockAdjustment->notes)
      <div class="alert alert-light border mb-4">
        <strong>{{ __('tables.columns.notes') }}:</strong> {{ $stockAdjustment->notes }}
      </div>
      @endif

      <div class="table-responsive">
        <table class="table table-bordered table-striped">
          <thead class="bg-light">
            <tr>
              <th>{{ __('tables.columns.item') }}</th>
              <th>{{ __('tables.columns.category') }}</th>
              <th class="text-right">{{ __('stock_adjustments.show.previous') }}</th>
              <th class="text-right">{{ __('stock_adjustments.show.new') }}</th>
              <th class="text-right">{{ __('stock_adjustments.show.change') }}</th>
              <th>{{ __('tables.columns.notes') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($stockAdjustment->items as $line)
              <tr>
                <td>{{ $line->item->name ?? '—' }}</td>
                <td>{{ $line->item->category->name ?? '—' }}</td>
                <td class="text-right">{{ number_format($line->previous_stock, 0) }} pcs</td>
                <td class="text-right font-weight-bold">{{ number_format($line->new_stock, 0) }} pcs</td>
                <td class="text-right font-weight-bold {{ $line->adjustment_qty >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ $line->adjustment_qty > 0 ? '+' : '' }}{{ number_format($line->adjustment_qty, 0) }} pcs
                </td>
                <td>{{ $line->line_notes ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
