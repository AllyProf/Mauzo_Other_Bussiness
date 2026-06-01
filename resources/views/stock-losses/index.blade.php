@extends('layouts.app')

@section('title', 'Stock Losses')

@section('styles')
<style>
  .stock-loss-modal-dialog {
    max-width: 900px;
  }
  .stock-loss-modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
  }
  .stock-loss-modal-header {
    background: #940000;
    color: #fff;
    align-items: center;
    padding: 1rem 1.25rem;
  }
  .stock-loss-modal-header .close {
    opacity: 1;
    text-shadow: none;
    margin: -0.5rem -0.5rem -0.5rem auto;
  }
  .stock-loss-modal-body {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
  }
  .stock-loss-modal-footer {
    background: #fff;
    border-top: 1px solid #dee2e6;
    padding: 0.85rem 1.25rem;
  }
  .stock-loss-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .stock-loss-step {
    flex: 1 1 140px;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
    color: #495057;
  }
  .stock-loss-step span {
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
  .stock-loss-panel {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem 1.15rem;
  }
  .stock-loss-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 0.75rem;
  }
  .stock-loss-table-wrap {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    max-height: 280px;
    overflow: auto;
  }
  .stock-loss-table-wrap table {
    margin-bottom: 0;
  }
  .stock-loss-table-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    box-shadow: 0 1px 0 #dee2e6;
  }
  #loss-items-table .qty-input {
    max-width: 100px;
    margin: 0 auto;
    text-align: center;
  }
  .stock-loss-summary-box {
    background: #fff;
    border: 1px solid #e9ecef;
    border-left: 4px solid #940000;
    border-radius: 6px;
    padding: 1rem 1.15rem;
    height: 100%;
  }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-minus-circle"></i> Stock Losses</h1>
    <p>Record items that were lost, damaged, destroyed, or expired</p>
  </div>
  @can('record_stock_loss')
  <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#recordLossModal">
    <i class="fa fa-plus"></i> Record Loss
  </button>
  @endcan
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-file-text-o fa-3x"></i>
      <div class="info">
        <h4>Records</h4>
        <p><b>{{ number_format($stats['total_records']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>Units Written Off</h4>
        <p><b>{{ number_format($stats['total_units_lost'], 2) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Cost Value Lost</h4>
        <p><b>{{ money($stats['total_cost_value']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Date</th>
              <th>Reason</th>
              <th>Items</th>
              <th>Qty Lost</th>
              <th>Cost Value</th>
              <th>Recorded By</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($losses as $loss)
              <tr>
                <td><strong>{{ $loss->reference_no }}</strong></td>
                <td>{{ $loss->loss_date->format('M d, Y') }}</td>
                <td>{{ $loss->reasonLabel() }}</td>
                <td>{{ $loss->items_count }}</td>
                <td>{{ number_format($loss->total_quantity, 2) }}</td>
                <td>{{ money($loss->total_cost_value) }}</td>
                <td>{{ $loss->user->name ?? 'N/A' }}</td>
                <td>
                  @if($loss->isCancelled())
                    <span class="badge badge-secondary">Cancelled</span>
                  @else
                    <span class="badge badge-danger">Recorded</span>
                  @endif
                </td>
                <td class="text-nowrap">
                  <a href="{{ route('stock-losses.show', $loss) }}" class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a>
                  @if(!$loss->isCancelled())
                    @canany(['cancel_stock_loss', 'record_stock_loss'])
                    <form action="{{ route('stock-losses.cancel', $loss) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-danger" onclick="confirmAction(event, 'Cancel this record?', 'Stock quantities will be restored to inventory.')">
                        <i class="fa fa-undo"></i>
                      </button>
                    </form>
                    @endcanany
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center text-muted py-4">No stock loss records yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
        {{ $losses->links() }}
      </div>
    </div>
  </div>
</div>

@include('stock-losses.partials.record-modal')
@endsection

@section('scripts')
@if($form)
<script>
  const itemsByCategory = @json($form['itemsByCategory']);
  const categoriesList = @json($form['categoriesList'] ?? []);
  const multiBusiness = @json($form['multiBusiness'] ?? false);
  const defaultEmptyMessage = multiBusiness
    ? 'Select business type and category above'
    : 'Select a category above to load items';

  function resetItemsTable(message) {
    $('#items-body').html(
      '<tr id="empty-row"><td colspan="6" class="text-center text-muted py-4">' +
      '<i class="fa fa-folder-open-o fa-lg d-block mb-2"></i>' +
      '<span id="empty-row-message">' + (message || defaultEmptyMessage) + '</span></td></tr>'
    );
    $('#grand-total, #loss-summary-total').text('TZS 0');
    $('#loss-line-count').text('0');
    $('#submit-btn').prop('disabled', true);
  }

  function rebuildCategoryOptions(businessTypeKey) {
    const $sel = $('#category-selector');
    $sel.empty();

    if (multiBusiness && !businessTypeKey) {
      $sel.append('<option value="">-- Select business first --</option>').prop('disabled', true);
      resetItemsTable();
      return;
    }

    $sel.append('<option value="">-- Select category --</option>').prop('disabled', false);

    categoriesList
      .filter(function (cat) {
        return !multiBusiness || String(cat.business_type_key) === String(businessTypeKey);
      })
      .forEach(function (cat) {
        $sel.append('<option value="' + cat.id + '">' + cat.name + '</option>');
      });

    if ($sel.find('option').length <= 1) {
      $sel.prop('disabled', true);
      resetItemsTable('No categories with stock for this business type');
    } else {
      resetItemsTable();
    }
  }

  function resetLossModalForm() {
    if (multiBusiness) {
      $('#business-type-selector').val('');
      rebuildCategoryOptions('');
    } else {
      $('#category-selector').val('');
      resetItemsTable();
    }
    $('#loss_notes').val('');
  }

  function formatTzs(amount) {
    return 'TZS ' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function updateRowTotal(row) {
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const unitCostText = row.find('.unit-cost-cell').text().replace(/[^\d.]/g, '');
    const unitCost = parseFloat(unitCostText) || 0;
    const total = qty * unitCost;
    row.find('.row-total').text(formatTzs(total));
    updateGrandTotal();
  }

  function updateGrandTotal() {
    let total = 0;
    let lineCount = 0;
    $('#items-body tr').each(function () {
      const qty = parseFloat($(this).find('.qty-input').val()) || 0;
      if (qty > 0) {
        lineCount++;
      }
      const text = $(this).find('.row-total').text().replace(/[^\d.]/g, '');
      total += parseFloat(text) || 0;
    });
    const formatted = formatTzs(total);
    $('#grand-total, #loss-summary-total').text(formatted);
    $('#loss-line-count').text(lineCount);

    const hasQty = lineCount > 0;
    $('#submit-btn').prop('disabled', !hasQty || $('#empty-row').length > 0);
  }

  $(function () {
    if (multiBusiness) {
      rebuildCategoryOptions('');

      $('#business-type-selector').on('change', function () {
        rebuildCategoryOptions($(this).val() || '');
      });
    }

    $('#category-selector').on('change', function () {
      const catId = $(this).val();
      const tbody = $('#items-body');
      tbody.empty();

      if (!catId || !itemsByCategory[catId] || itemsByCategory[catId].length === 0) {
        resetItemsTable('No in-stock items in this category');
        return;
      }

      $.each(itemsByCategory[catId], function (idx, item) {
        const row = `
          <tr data-item-id="${item.id}">
            <td class="align-middle">
              <strong>${item.name}</strong>
              <small class="text-muted d-block">${item.sku || 'No SKU'} · ${item.unit}</small>
              <input type="hidden" name="items[${idx}][id]" value="${item.id}">
            </td>
            <td class="align-middle text-center stock-cell font-weight-bold">${Number(item.stock).toLocaleString()}</td>
            <td class="align-middle text-center">
              <input type="number" name="items[${idx}][qty]" class="form-control form-control-sm qty-input" value="0" min="0" max="${item.stock}" step="0.01">
            </td>
            <td class="align-middle text-right unit-cost-cell">${formatTzs(item.unit_cost)}</td>
            <td class="row-total align-middle text-right text-danger font-weight-bold">${formatTzs(0)}</td>
            <td class="align-middle">
              <input type="text" name="items[${idx}][line_notes]" class="form-control form-control-sm" placeholder="Optional note">
            </td>
          </tr>`;
        tbody.append(row);
      });

      updateGrandTotal();
    });

    $(document).on('input change', '#recordLossModal .qty-input', function () {
      const row = $(this).closest('tr');
      const max = parseFloat($(this).attr('max')) || 0;
      let qty = parseFloat($(this).val()) || 0;
      if (qty > max) {
        qty = max;
        $(this).val(max);
      }
      if (qty < 0) {
        qty = 0;
        $(this).val(0);
      }
      updateRowTotal(row);
    });

    $('#loss-form').on('submit', function (e) {
      let hasQty = false;
      $('#items-body .qty-input').each(function () {
        if (parseFloat($(this).val()) > 0) {
          hasQty = true;
        }
      });
      if (!hasQty) {
        e.preventDefault();
        alert('Enter quantity lost for at least one item.');
      }
    });

    $('#recordLossModal').on('hidden.bs.modal', function () {
      resetLossModalForm();
    });

    @if($errors->any() || request('record'))
      $('#recordLossModal').modal('show');
    @endif
  });
</script>
@endif
@endsection
