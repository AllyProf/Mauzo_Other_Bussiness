@extends('layouts.app')

@section('title', __('stock_adjustments.title'))

@section('styles')
<style>
  .stock-adjust-modal-dialog { max-width: 920px; }
  .stock-adjust-modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
  }
  .stock-adjust-modal-header {
    background: #940000;
    color: #fff;
    align-items: center;
    padding: 1rem 1.25rem;
  }
  .stock-adjust-modal-header .close {
    opacity: 1;
    text-shadow: none;
    margin: -0.5rem -0.5rem -0.5rem auto;
  }
  .stock-adjust-modal-body {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
  }
  .stock-adjust-modal-footer {
    background: #fff;
    border-top: 1px solid #dee2e6;
    padding: 0.85rem 1.25rem;
  }
  .stock-adjust-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .stock-adjust-step {
    flex: 1 1 140px;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
    color: #495057;
  }
  .stock-adjust-step span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #940000;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    margin-right: 0.5rem;
  }
  .stock-adjust-panel {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem 1.15rem;
  }
  .stock-adjust-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 0.75rem;
  }
  .stock-adjust-table-wrap {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    max-height: 320px;
    overflow: auto;
  }
  .stock-adjust-table-wrap table { margin-bottom: 0; }
  .stock-adjust-table-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    box-shadow: 0 1px 0 #dee2e6;
  }
  .new-stock-input {
    max-width: 100px;
    margin: 0 auto;
    text-align: center;
    -moz-appearance: textfield;
  }
  .new-stock-input::-webkit-outer-spin-button,
  .new-stock-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  .change-positive { color: #28a745; font-weight: 700; }
  .change-negative { color: #dc3545; font-weight: 700; }
  .change-neutral { color: #6c757d; }

  @media (max-width: 991.98px) {
    .stock-adjustments-page .app-title {
      flex-wrap: wrap;
      align-items: flex-start !important;
      gap: 10px;
    }
    .stock-adjustments-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .stock-adjustments-page .app-title p {
      display: block !important;
      font-size: 0.88rem;
      font-style: normal;
    }
    .stock-adjustments-page .app-title > div .btn {
      width: 100%;
      margin-left: 0 !important;
    }
  }

  @media (max-width: 767.98px) {
    .stock-adjust-modal-dialog {
      max-width: calc(100vw - 1rem);
      margin: 0.5rem auto;
    }
    .stock-adjust-modal-body { padding: 1rem; }
    .stock-adjust-step { flex: 1 1 100%; }
    .new-stock-input { max-width: none; width: 100%; }
    .stock-adjust-table-wrap { max-height: 240px; }
    .stock-adjustments-page .adjustments-col-hide-mobile { display: none !important; }
  }
</style>
@endsection

@section('content')
<div class="stock-adjustments-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-sliders"></i> {{ __('stock_adjustments.title') }}</h1>
    <p>{{ __('stock_adjustments.subtitle') }}</p>
  </div>
  @if($canAdjust)
  <div>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#adjustStockModal">
      <i class="fa fa-plus"></i> {{ __('stock_adjustments.new_adjustment') }}
    </button>
  </div>
  @endif
</div>

<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>{{ __('stock_adjustments.danger_zone') }}</strong>
  <span class="d-block small text-muted mt-1 mb-0">{{ __('stock_adjustments.danger_hint') }}</span>
</div>

<div class="row mb-3">
  <div class="col-6 col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-list-alt fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock_adjustments.stats.records') }}</h4>
        <p><b>{{ $stats['total_records'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock_adjustments.stats.lines') }}</h4>
        <p><b>{{ $stats['total_lines'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="widget-small warning coloured-icon">
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
      <div class="tile-body">
        <h3 class="tile-title mb-3">{{ __('stock_adjustments.history') }}</h3>
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0">
            <thead class="thead-light">
              <tr>
                <th>{{ __('stock_adjustments.show.reference') }}</th>
                <th>{{ __('stock_adjustments.show.date') }}</th>
                <th class="adjustments-col-hide-mobile">{{ __('stock_adjustments.show.reason') }}</th>
                <th class="adjustments-col-hide-mobile">{{ __('categories.items_count') }}</th>
                <th>{{ __('stock_adjustments.show.net_change') }}</th>
                <th class="adjustments-col-hide-mobile">{{ __('stock_adjustments.show.recorded_by') }}</th>
                <th>{{ __('stock_adjustments.show.status') }}</th>
                <th>{{ __('tables.columns.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($adjustments as $adjustment)
                <tr>
                  <td><strong class="text-primary">{{ $adjustment->reference_no }}</strong></td>
                  <td>{{ $adjustment->adjustment_date->format('d M Y') }}</td>
                  <td class="adjustments-col-hide-mobile">{{ $adjustment->reasonLabel() }}</td>
                  <td class="adjustments-col-hide-mobile">{{ $adjustment->items_count }}</td>
                  <td>
                    @php $net = (float) $adjustment->net_adjustment; @endphp
                    <span class="{{ $net > 0 ? 'change-positive' : ($net < 0 ? 'change-negative' : 'change-neutral') }}">
                      {{ $net > 0 ? '+' : '' }}{{ number_format($net, 0) }}
                    </span>
                  </td>
                  <td class="adjustments-col-hide-mobile">{{ $adjustment->user->name ?? '—' }}</td>
                  <td>
                    @if($adjustment->isCancelled())
                      <span class="badge badge-secondary">{{ __('stock_adjustments.status.cancelled') }}</span>
                    @else
                      <span class="badge badge-success">{{ __('stock_adjustments.status.completed') }}</span>
                    @endif
                  </td>
                  <td class="text-nowrap">
                    <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-outline-primary" title="{{ __('common.view') ?? 'View' }}"><i class="fa fa-eye"></i></a>
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
      const currentCell = item.stock_label
        ? '<span class="font-weight-bold d-block text-left" style="font-size:0.85rem;line-height:1.35;">' + item.stock_label + '</span>'
        : '<span class="font-weight-bold d-block">' + Number(item.stock).toLocaleString() + '</span><small class="text-muted">pcs</small>';
      tbody.append(`
        <tr data-item-id="${item.id}" data-current="${item.stock}">
          <td>
            <strong>${item.name}</strong>
            <small class="text-muted d-block">${item.sku || 'No SKU'}</small>
            <input type="hidden" name="items[${idx}][id]" value="${item.id}">
          </td>
          <td class="text-center align-middle">
            ${currentCell}
          </td>
          <td class="text-center align-middle">
            <input type="number" name="items[${idx}][new_stock]" class="form-control form-control-sm new-stock-input" value="${item.stock}" min="0" step="0.01" inputmode="decimal" title="Total pieces in stock">
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
