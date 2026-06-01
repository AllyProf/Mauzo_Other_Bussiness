@extends('layouts.app')

@section('title', 'Close Shift')

@section('styles')
<style>
  .stock-check-table input[type="number"] { max-width: 110px; }
  .variance-ok { color: #28a745; font-weight: bold; }
  .variance-bad { color: #dc3545; font-weight: bold; }
  .sticky-actions { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #dee2e6; padding: 15px; z-index: 5; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-stop"></i> Close Shift — Final Stock Check</h1>
    <p>Count stock again, then close your shift. You will continue on <strong>Daily Reconciliation</strong> to submit handover.</p>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i><div class="info"><h4>Sales</h4><p><b>{{ $shift->sales_count }}</b></p></div></div></div>
  <div class="col-md-4"><div class="widget-small info coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i><div class="info"><h4>Gross</h4><p><b>TZS {{ number_format($shift->gross_sales, 0) }}</b></p></div></div></div>
  <div class="col-md-4"><div class="widget-small success coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>Collected</h4><p><b>TZS {{ number_format($shift->amount_collected, 0) }}</b></p></div></div></div>
</div>

<div class="tile">
  <form action="{{ route('shifts.close.store', $shift) }}" method="POST" id="closeShiftForm">
    @csrf

    <div class="form-group">
      <label class="control-label">Closing Notes (optional)</label>
      <textarea name="closing_notes" class="form-control" rows="2" placeholder="Handover notes, issues, etc.">{{ old('closing_notes') }}</textarea>
    </div>

    <div class="mb-3">
      <input type="text" id="itemSearch" class="form-control" placeholder="Search items...">
    </div>

    <div class="table-responsive" style="max-height: 50vh; overflow-y: auto;">
      <table class="table table-bordered table-sm stock-check-table">
        <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
          <tr>
            <th>Item</th>
            <th class="text-right">System Stock Now</th>
            <th class="text-right">Physical Count</th>
            <th class="text-right">Variance</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            @php
              $system = (float) $item->current_stock;
              $counted = old('counts.'.$item->id, $system);
              $variance = (float) $counted - $system;
            @endphp
            <tr class="stock-row" data-search="{{ strtolower($item->name.' '.($item->sku ?? '')) }}">
              <td><strong>{{ $item->name }}</strong></td>
              <td class="text-right system-stock">{{ number_format($system, 2) }}</td>
              <td class="text-right">
                <input type="number" name="counts[{{ $item->id }}]" class="form-control form-control-sm counted-stock ml-auto" min="0" step="0.01" value="{{ $counted }}" required>
              </td>
              <td class="text-right variance-cell {{ abs($variance) < 0.001 ? 'variance-ok' : 'variance-bad' }}">{{ number_format($variance, 2) }}</td>
              <td><input type="text" name="notes[{{ $item->id }}]" class="form-control form-control-sm" value="{{ old('notes.'.$item->id) }}"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="sticky-actions d-flex justify-content-between align-items-center">
      <a href="{{ route('shifts.show', $shift) }}" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-warning btn-lg" onclick="return confirm('Close this shift and go to Daily Reconciliation for handover? You will need a new shift to sell again.');">
        <i class="fa fa-balance-scale"></i> Close Shift &amp; Go to Handover
      </button>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  $('#itemSearch').on('input', function() {
    const q = $(this).val().toLowerCase().trim();
    $('.stock-row').each(function() {
      $(this).toggle(!q || ($(this).data('search') || '').indexOf(q) !== -1);
    });
  });
  $('.counted-stock').on('input', function() {
    const $row = $(this).closest('tr');
    const system = parseFloat($row.find('.system-stock').text().replace(/,/g, '')) || 0;
    const counted = parseFloat($(this).val()) || 0;
    const variance = counted - system;
    const $cell = $row.find('.variance-cell');
    $cell.text(variance.toFixed(2)).toggleClass('variance-ok', Math.abs(variance) < 0.001).toggleClass('variance-bad', Math.abs(variance) >= 0.001);
  });
});
</script>
@endsection
