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
  .count-pcs-wrap { display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px; }
  .count-pcs-label { font-size: 0.75rem; color: #6c757d; font-weight: 600; white-space: nowrap; }
  .bulk-count-hint { display: block; font-size: 0.72rem; color: #6c757d; margin-top: 4px; }
</style>
@endsection

@section('content')
@php
  $hasBulkItems = $items->contains(fn ($item) => ($item->stock_info['has_bulk_stock'] ?? false));
@endphp
<div class="app-title" data-tour="shift-stock-check">
  <div>
    <h1><i class="fa fa-clipboard"></i> Open Shift — Physical Stock Check</h1>
    <p>Verify stock on hand before selling. Enter physical count in <strong>pieces (pcs)</strong> only — change only where different from system stock.</p>
  </div>
  <a href="{{ route('shifts.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
</div>

@include('home.partials.my-stock-shortages')

@if(!empty($assignedBusinessLabel) || !empty($assignedBranchName))
<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-user"></i>
  Your stock check shows items for
  @if(!empty($assignedBranchName)) branch <strong>{{ $assignedBranchName }}</strong>@endif
  @if(!empty($assignedBranchName) && !empty($assignedBusinessLabel)) · @endif
  @if(!empty($assignedBusinessLabel)) business <strong>{{ $assignedBusinessLabel }}</strong>@endif
  only.
</div>
@endif

<div class="tile">
  <form action="{{ route('shifts.store') }}" method="POST" id="openShiftForm">
    @csrf

    @if($items->isEmpty())
      <div class="alert alert-warning mb-0">
        <i class="fa fa-exclamation-triangle"></i> No items with stock on hand. Receive stock or add items before opening a shift.
      </div>
    @else
      @if($hasBulkItems)
        <div class="alert alert-light border small mb-3 py-2">
          <i class="fa fa-info-circle text-primary"></i>
          Items sold by <strong>box and piece</strong> (e.g. 74 pcs · 7 Box) need only one count — enter the <strong>total pieces</strong> you physically have. Box totals in System Stock are for reference.
        </div>
      @endif

      <div class="d-flex flex-wrap align-items-center justify-content-end mb-3">
        <div class="stock-search-wrap">
          <input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search by name or SKU...">
        </div>
      </div>

      <div class="table-responsive" style="max-height: 55vh; overflow-y: auto;">
        <table class="table table-bordered table-sm stock-check-table" id="stockCheckTable">
          <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
            <tr>
              <th>{{ __('tables.columns.item') }}</th>
              <th>{{ __('tables.columns.category') }}</th>
              <th class="text-right">System Stock</th>
              <th class="text-right">Physical Count <small class="text-muted">(pcs only)</small></th>
              <th class="text-right">Variance</th>
              <th>{{ __('tables.columns.reason_notes') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $item)
              @php
                $stockInfo = $item->stock_info ?? app(\App\Services\ItemStockDisplayService::class)->format($item);
                $system = (float) $stockInfo['pieces'];
                $counted = old('counts.'.$item->id, $system);
                $variance = (float) $counted - $system;
                $hasBulk = $stockInfo['has_bulk_stock'] ?? false;
              @endphp
              <tr class="stock-row"
                  data-search="{{ strtolower($item->name.' '.($item->sku ?? '').' '.($item->category->name ?? '')) }}"
                  data-system="{{ $system }}"
                  @if($hasBulk) data-pack-size="{{ $stockInfo['pack_size'] }}" data-bulk-name="{{ $stockInfo['bulk_name'] }}" @endif>
                <td>
                  <strong>{{ $item->name }}</strong>
                  @if($item->sku)<br><small class="text-muted">{{ $item->sku }}</small>@endif
                </td>
                <td>{{ $item->category->name ?? '—' }}</td>
                <td class="text-right system-stock">{{ $stockInfo['stock_display'] }}</td>
                <td class="text-right">
                  <div class="count-pcs-wrap ml-auto">
                    <input type="number"
                           name="counts[{{ $item->id }}]"
                           class="form-control form-control-sm counted-stock"
                           min="0"
                           step="{{ $hasBulk ? '1' : '0.01' }}"
                           value="{{ fmod((float) $counted, 1.0) === 0.0 ? (int) $counted : $counted }}"
                           required>
                    <span class="count-pcs-label">pcs</span>
                  </div>
                  @if($hasBulk)
                    <small class="bulk-count-hint"></small>
                  @endif
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
  function formatBulkHint(pieces, packSize, bulkName) {
    packSize = Math.max(1, parseInt(packSize, 10) || 1);
    bulkName = bulkName || 'Box';
    pieces = Math.max(0, parseFloat(pieces) || 0);
    const fullBoxes = Math.floor(pieces / packSize);
    const loose = Math.round((pieces - (fullBoxes * packSize)) * 100) / 100;

    if (fullBoxes > 0 && loose > 0.0001) {
      return '= ' + fullBoxes + ' ' + bulkName + ' + ' + (loose % 1 === 0 ? loose : loose.toFixed(2)) + ' pcs';
    }
    if (fullBoxes > 0) {
      return '= ' + fullBoxes + ' ' + bulkName;
    }
    return '= ' + (pieces % 1 === 0 ? pieces : pieces.toFixed(2)) + ' pcs';
  }

  function needsReason($row) {
    const system = parseFloat($row.data('system')) || 0;
    const counted = parseFloat($row.find('.counted-stock').val()) || 0;
    return counted < system - 0.0001;
  }

  function updateBulkHint($row) {
    const packSize = $row.data('pack-size');
    if (!packSize) {
      return;
    }

    const counted = parseFloat($row.find('.counted-stock').val()) || 0;
    $row.find('.bulk-count-hint').text(formatBulkHint(counted, packSize, $row.data('bulk-name')));
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

    updateBulkHint($row);
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
