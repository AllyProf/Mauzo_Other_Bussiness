@extends('layouts.app')

@section('title', __('stock_adjustments.title'))

@section('styles')
<style>
  .danger-zone-banner {
    border-left: 4px solid #dc3545;
    background: #fff5f5;
  }
  .danger-adjust-modal-dialog { max-width: 920px; }
  .danger-adjust-modal-content { border: none; border-radius: 8px; overflow: hidden; }
  .danger-adjust-modal-header {
    background: linear-gradient(135deg, #c0392b, #922b21);
    color: #fff;
    padding: 1rem 1.25rem;
  }
  .danger-adjust-modal-body { padding: 1.25rem 1.5rem; background: #f8f9fa; }
  .danger-adjust-modal-footer { background: #fff; border-top: 1px solid #dee2e6; }
  .danger-adjust-panel {
    background: #fff;
    border: 1px solid #f1c0c0;
    border-radius: 8px;
    padding: 1rem 1.15rem;
  }
  .danger-adjust-table-wrap {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    max-height: 320px;
    overflow: auto;
  }
  .change-positive { color: #28a745; font-weight: 700; }
  .change-negative { color: #dc3545; font-weight: 700; }
  .change-neutral { color: #6c757d; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1 class="text-danger"><i class="fa fa-exclamation-triangle"></i> {{ __('stock_adjustments.title') }}</h1>
    <p>{{ __('stock_adjustments.subtitle') }}</p>
  </div>
  @if($canAdjust)
  <div>
    <button type="button" class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#adjustStockModal">
      <i class="fa fa-wrench"></i> {{ __('stock_adjustments.new_adjustment') }}
    </button>
  </div>
  @endif
</div>

<div class="alert danger-zone-banner py-3 mb-3">
  <strong class="text-danger"><i class="fa fa-warning"></i> {{ __('stock_adjustments.danger_zone') }}</strong>
  <span class="d-block small text-muted mt-1">{{ __('stock_adjustments.danger_hint') }}</span>
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-list-alt fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock_adjustments.stats.records') }}</h4>
        <p><b>{{ $stats['total_records'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock_adjustments.stats.lines') }}</h4>
        <p><b>{{ $stats['total_lines'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-exchange fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock_adjustments.stats.net_change') }}</h4>
        <p><b>{{ number_format($stats['net_adjustment'], 0) }}</b> pcs</p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">{{ __('stock_adjustments.history') }}</h3>
      <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead class="bg-light">
            <tr>
              <th>{{ __('stock_adjustments.show.reference') }}</th>
              <th>{{ __('stock_adjustments.show.date') }}</th>
              <th>{{ __('stock_adjustments.show.reason') }}</th>
              <th>{{ __('categories.items_count') }}</th>
              <th>{{ __('stock_adjustments.show.net_change') }}</th>
              <th>{{ __('stock_adjustments.show.recorded_by') }}</th>
              <th>{{ __('stock_adjustments.show.status') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($adjustments as $adjustment)
              <tr>
                <td><strong class="text-danger">{{ $adjustment->reference_no }}</strong></td>
                <td>{{ $adjustment->adjustment_date->format('d M Y') }}</td>
                <td>{{ $adjustment->reasonLabel() }}</td>
                <td>{{ $adjustment->items_count }}</td>
                <td>
                  @php $net = (float) $adjustment->net_adjustment; @endphp
                  <span class="{{ $net > 0 ? 'change-positive' : ($net < 0 ? 'change-negative' : 'change-neutral') }}">
                    {{ $net > 0 ? '+' : '' }}{{ number_format($net, 0) }}
                  </span>
                </td>
                <td>{{ $adjustment->user->name ?? '—' }}</td>
                <td>
                  @if($adjustment->isCancelled())
                    <span class="badge badge-secondary">{{ __('stock_adjustments.status.cancelled') }}</span>
                  @else
                    <span class="badge badge-danger">{{ __('stock_adjustments.status.completed') }}</span>
                  @endif
                </td>
                <td class="text-nowrap">
                  <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-outline-danger"><i class="fa fa-eye"></i></a>
                  @if(!$adjustment->isCancelled() && $canAdjust)
                  <form action="{{ route('stock-adjustments.cancel', $adjustment) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="confirmAction(event, @json(__('stock_adjustments.cancel_confirm_title')), @json(__('stock_adjustments.cancel_confirm_text')))">
                      <i class="fa fa-undo"></i>
                    </button>
                  </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No adjustment records yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $adjustments->links() }}
    </div>
  </div>
</div>

@if($form ?? null)
@include('stock-adjustments.partials.adjust-modal')
@endif
@endsection

@section('scripts')
@if($form)
<script>
const adjustItemsByCategory = @json($form['itemsByCategory']);
const adjustCategoriesList = @json($form['categoriesList'] ?? []);
const adjustMultiBusiness = @json($form['multiBusiness'] ?? false);

function formatChange(delta) {
  const n = Number(delta) || 0;
  if (Math.abs(n) < 0.0001) return '<span class="change-neutral">0</span>';
  const cls = n > 0 ? 'change-positive' : 'change-negative';
  const sign = n > 0 ? '+' : '';
  return '<span class="' + cls + '">' + sign + n.toLocaleString() + '</span>';
}

function resetAdjustTable(message) {
  $('#adjust-items-body').html('<tr id="adjust-empty-row"><td colspan="5" class="text-center text-muted py-4">' + (message || '{{ __('stock_adjustments.modal.step_category') }}') + '</td></tr>');
  updateAdjustSubmit();
}

function rebuildAdjustCategories(businessTypeKey) {
  const $sel = $('#adjust-category');
  $sel.empty().append('<option value="">--</option>');
  if (adjustMultiBusiness && !businessTypeKey) {
    $sel.prop('disabled', true);
    resetAdjustTable();
    return;
  }
  $sel.prop('disabled', false);
  adjustCategoriesList
    .filter(cat => !adjustMultiBusiness || String(cat.business_type_key) === String(businessTypeKey))
    .forEach(cat => $sel.append('<option value="' + cat.id + '">' + cat.name + '</option>'));
  resetAdjustTable();
}

function updateAdjustSubmit() {
  let hasChange = false;
  $('#adjust-items-body tr[data-item-id]').each(function () {
    const current = parseFloat($(this).data('current')) || 0;
    const next = parseFloat($(this).find('.new-stock-input').val());
    if (!isNaN(next) && Math.abs(next - current) > 0.0001) hasChange = true;
  });
  const confirmed = $('#confirm_ack').is(':checked');
  const reason = $('select[name="reason"]').val();
  $('#adjust-submit-btn').prop('disabled', !(hasChange && confirmed && reason));
}

$(function () {
  if (adjustMultiBusiness) {
    rebuildAdjustCategories('');
    $('#adjust-business-type').on('change', function () {
      rebuildAdjustCategories($(this).val() || '');
    });
  }

  $('#adjust-category').on('change', function () {
    const catId = $(this).val();
    const tbody = $('#adjust-items-body');
    tbody.empty();
    if (!catId || !adjustItemsByCategory[catId] || !adjustItemsByCategory[catId].length) {
      resetAdjustTable('{{ __('common.no_items') ?? 'No items in this category' }}');
      return;
    }
    adjustItemsByCategory[catId].forEach(function (item, idx) {
      const breakdown = item.stock_label
        ? '<small class="text-info d-block mt-1"><i class="fa fa-cubes"></i> ' + item.stock_label + '</small>'
        : '';
      const pkgHint = item.stock_breakdown
        ? '<small class="text-muted d-block">(' + item.stock_breakdown + ')</small>'
        : '';
      tbody.append(`
        <tr data-item-id="${item.id}" data-current="${item.stock}">
          <td>
            <strong>${item.name}</strong>
            <small class="text-muted d-block">${item.sku || 'No SKU'}</small>
            ${breakdown}
            ${pkgHint}
            <input type="hidden" name="items[${idx}][id]" value="${item.id}">
          </td>
          <td class="text-center align-middle">
            <span class="font-weight-bold d-block">${Number(item.stock).toLocaleString()}</span>
            <small class="text-muted">pcs</small>
          </td>
          <td class="text-center align-middle">
            <input type="number" name="items[${idx}][new_stock]" class="form-control form-control-sm new-stock-input text-center" value="${item.stock}" min="0" step="0.01" title="Total pieces in stock">
            <small class="text-muted">pcs</small>
          </td>
          <td class="text-center align-middle change-cell">${formatChange(0)}</td>
          <td class="align-middle">
            <input type="text" name="items[${idx}][line_notes]" class="form-control form-control-sm" placeholder="">
          </td>
        </tr>
      `);
    });
    updateAdjustSubmit();
  });

  $(document).on('input change', '.new-stock-input', function () {
    const row = $(this).closest('tr');
    const current = parseFloat(row.data('current')) || 0;
    let next = parseFloat($(this).val());
    if (isNaN(next) || next < 0) next = 0;
    row.find('.change-cell').html(formatChange(next - current));
    updateAdjustSubmit();
  });

  $('#confirm_ack, select[name="reason"]').on('change', updateAdjustSubmit);

  @if(request('adjust') || $errors->any())
  $('#adjustStockModal').modal('show');
  @endif
});
</script>
@endif
@endsection
