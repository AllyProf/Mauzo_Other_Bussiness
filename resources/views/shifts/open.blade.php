@extends('layouts.app')

@section('title', 'Open Shift — Stock Check')

@section('styles')
<style>
  .stock-check-table input[type="number"] { max-width: 110px; }
  .variance-ok { color: #28a745; font-weight: bold; }
  .variance-bad { color: #dc3545; font-weight: bold; }
  .variance-short { color: #dc3545; font-weight: bold; }
  .sticky-actions {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid #dee2e6;
    padding: 15px;
    z-index: 5;
  }
  .stock-search-wrap { max-width: 320px; }
  .reason-required-label { display: none; color: #dc3545; font-size: 0.75rem; }
  .stock-row.needs-reason .reason-required-label { display: block; }
  .stock-row.needs-reason .item-note { border-color: #dc3545; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clipboard"></i> Open Shift — Physical Stock Check</h1>
    <p>Verify stock on hand before selling. Physical count defaults to system — change only where different.</p>
  </div>
  <a href="{{ route('shifts.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<div class="tile">
  <form action="{{ route('shifts.store') }}" method="POST" id="openShiftForm">
    @csrf

    @if($items->isEmpty())
      <div class="alert alert-warning mb-0">
        <i class="fa fa-exclamation-triangle"></i> No items with stock on hand. Receive stock or add items before opening a shift.
      </div>
    @else
      <div class="d-flex flex-wrap align-items-center justify-content-end mb-3">
        <div class="stock-search-wrap">
          <input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search by name or SKU...">
        </div>
      </div>

      <div class="table-responsive" style="max-height: 55vh; overflow-y: auto;">
        <table class="table table-bordered table-sm stock-check-table" id="stockCheckTable">
          <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
            <tr>
              <th>Item</th>
              <th>Category</th>
              <th class="text-right">System Stock</th>
              <th class="text-right">Physical Count <small class="text-muted">(pieces)</small></th>
              <th class="text-right">Variance</th>
              <th>Reason / Notes</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $item)
              @php
                $system = (float) $item->current_stock;
                $counted = old('counts.'.$item->id, $system);
                $variance = (float) $counted - $system;
              @endphp
              <tr class="stock-row" data-search="{{ strtolower($item->name.' '.($item->sku ?? '').' '.($item->category->name ?? '')) }}" data-system="{{ $system }}">
                <td>
                  <strong>{{ $item->name }}</strong>
                  @if($item->sku)<br><small class="text-muted">{{ $item->sku }}</small>@endif
                </td>
                <td>{{ $item->category->name ?? '—' }}</td>
                <td class="text-right system-stock">{{ $item->stockDisplay() }}</td>
                <td class="text-right">
                  <input type="number" name="counts[{{ $item->id }}]" class="form-control form-control-sm counted-stock ml-auto" min="0" step="0.01" value="{{ $counted }}" required>
                </td>
                <td class="text-right variance-cell {{ abs($variance) < 0.001 ? 'variance-ok' : ($variance < 0 ? 'variance-short' : 'variance-bad') }}">{{ number_format($variance, 2) }}</td>
                <td>
                  <input type="text" name="notes[{{ $item->id }}]" class="form-control form-control-sm item-note" value="{{ old('notes.'.$item->id) }}" placeholder="Optional unless count is lower">
                  <small class="reason-required-label">Reason required — physical count is below system stock</small>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="form-group mt-4 mb-0">
        <label class="control-label">Opening Notes (optional)</label>
        <textarea name="opening_notes" class="form-control" rows="2" placeholder="Any general remarks before starting shift...">{{ old('opening_notes') }}</textarea>
      </div>

      <div class="sticky-actions text-right mt-3">
        <button type="submit" class="btn btn-success btn-lg">
          <i class="fa fa-check"></i> Confirm Stock &amp; Open Shift
        </button>
      </div>
    @endif
  </form>
</div>
@endsection

@section('scripts')
@if(!$items->isEmpty())
<script>
jQuery(function($) {
  function needsReason($row) {
    const system = parseFloat($row.data('system')) || 0;
    const counted = parseFloat($row.find('.counted-stock').val()) || 0;
    return counted < system - 0.0001;
  }

  function updateVariance($row) {
    const system = parseFloat($row.data('system')) || 0;
    const counted = parseFloat($row.find('.counted-stock').val()) || 0;
    const variance = counted - system;
    const $cell = $row.find('.variance-cell');

    $cell.text(variance.toFixed(2));
    $cell.removeClass('variance-ok variance-bad variance-short');

    if (Math.abs(variance) < 0.001) {
      $cell.addClass('variance-ok');
      $row.removeClass('needs-reason');
    } else if (variance < 0) {
      $cell.addClass('variance-short');
      $row.addClass('needs-reason');
    } else {
      $cell.addClass('variance-bad');
      $row.removeClass('needs-reason');
    }
  }

  $('#itemSearch').on('input', function() {
    const q = $(this).val().toLowerCase().trim();
    $('.stock-row').each(function() {
      const text = $(this).data('search') || '';
      $(this).toggle(!q || text.indexOf(q) !== -1);
    });
  });

  $('.counted-stock, .item-note').on('input', function() {
    updateVariance($(this).closest('tr'));
  });

  $('#openShiftForm').on('submit', function(e) {
    let firstInvalid = null;

    $('.stock-row').each(function() {
      const $row = $(this);
      if (needsReason($row) && !$.trim($row.find('.item-note').val())) {
        firstInvalid = $row;
        return false;
      }
    });

    if (firstInvalid) {
      e.preventDefault();
      const name = $.trim(firstInvalid.find('td:first strong').text());
      firstInvalid.find('.item-note').focus();
      Swal.fire({
        icon: 'warning',
        title: 'Reason required',
        text: 'Physical count for ' + name + ' is lower than system stock. Please write a reason.',
        confirmButtonColor: '#940000'
      });
    }
  });

  $('.stock-row').each(function() {
    updateVariance($(this));
  });
});
</script>
@endif
@endsection
