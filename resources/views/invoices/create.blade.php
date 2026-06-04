@extends('layouts.app')

@section('title', 'Create Invoice')

@section('content')
@php
  $multiBusiness = count($businessTypes ?? []) > 1;
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-file-text-o"></i> Create Invoice</h1>
    <p>Create the invoice first — record payment later from the Invoices list (payment methods are in Settings)</p>
  </div>
  <a href="{{ route('invoices.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Invoices</a>
</div>

@if($openShift ?? false)
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-clock-o"></i> Shift #{{ $openShift->id }} is open.
</div>
@endif

@if(!empty($activeBranchName))
<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-map-marker"></i>
  Showing items for <strong>{{ $activeBranchName }}</strong>. Switch branch in the header to change location.
</div>
@elseif($viewingAllBranches ?? false)
<div class="alert alert-light border py-2 mb-3">
  <i class="fa fa-building"></i>
  Viewing items from <strong>all branches</strong>. Switch branch in the header to filter invoice items.
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <form action="{{ route('invoices.store') }}" method="POST" id="invoiceForm">
        @csrf
        <div class="row mb-4">
          <div class="col-md-3">
            <label class="font-weight-bold">Invoice Date</label>
            <input type="date" name="sale_date" class="form-control" value="{{ old('sale_date', date('Y-m-d')) }}" required>
          </div>
          <div class="col-md-9">
            <label class="font-weight-bold">Customer</label>
            <select name="customer_id" id="customerSelect" class="form-control">
              <option value="">Walk-in / enter manually below</option>
                @foreach($customers as $customer)
                <option value="{{ $customer->id }}" data-name="{{ e($customer->name) }}" data-phone="{{ e($customer->phone ?? '') }}" data-email="{{ e($customer->email ?? '') }}"
                  {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                  {{ $customer->name }}@if($customer->phone) — {{ $customer->phone }}@endif
                </option>
                @endforeach
            </select>
            <div class="row mt-2" id="manualCustomerFields">
              <div class="col-md-4">
                <input type="text" name="customer_name" id="customerName" class="form-control" placeholder="Customer name (optional)" value="{{ old('customer_name') }}">
              </div>
              <div class="col-md-4">
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
                  <input type="text" name="customer_phone" id="customerPhone" class="form-control" placeholder="Phone for SMS" value="{{ old('customer_phone') }}">
                </div>
              </div>
              <div class="col-md-4">
                <input type="email" name="customer_email" id="customerEmail" class="form-control" placeholder="Email for invoice attachment" value="{{ old('customer_email') }}">
              </div>
            </div>
            <small class="text-muted d-block mt-1">SMS is sent to the phone number; the invoice is emailed as a PDF when an address is provided.</small>
          </div>
        </div>

        <h5 class="mb-2"><i class="fa fa-list"></i> Line Items</h5>
        @if($multiBusiness)
        <p class="text-muted small mb-3">
          For each line, first choose <strong>which business</strong> (e.g. Spare Parts or Grocery), then pick the item.
          You can mix different businesses on the same invoice.
        </p>
        @endif

        <div class="table-responsive">
          <table class="table table-bordered" id="linesTable">
            <thead class="thead-light">
              <tr>
                @if($multiBusiness)
                <th style="width:22%">Business</th>
                <th style="width:30%">Item</th>
                @else
                <th style="width:40%">Item</th>
                @endif
                <th style="width:12%">Qty</th>
                <th style="width:18%">Unit Price</th>
                <th style="width:18%">Line Total</th>
                <th style="width:12%"></th>
              </tr>
            </thead>
            <tbody id="linesBody">
              <tr class="line-row">
                @if($multiBusiness)
                <td>
                  <select class="form-control line-business-type" required>
                    <option value="">Select business...</option>
                    @foreach($businessTypes as $type)
                      <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                    @endforeach
                  </select>
                </td>
                @endif
                <td>
                  <select name="items[0][id]" class="form-control item-select" {{ $multiBusiness ? 'disabled' : 'required' }}>
                    <option value="">Select item...</option>
                    @if(!$multiBusiness)
                      @foreach($catalogItems as $item)
                        <option value="{{ $item['id'] }}" data-price="{{ $item['price'] }}" data-stock="{{ $item['stock'] }}">
                          {{ $item['name'] }} ({{ $item['sku'] }}) — {{ money($item['price']) }} · Stock: {{ $item['stock'] }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </td>
                <td><input type="number" name="items[0][qty]" class="form-control qty-input" min="1" value="1" required></td>
                <td><input type="number" name="items[0][price]" class="form-control price-input" min="0" step="0.01" required></td>
                <td class="line-total align-middle font-weight-bold text-right">0</td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-sm btn-danger remove-line" disabled><i class="fa fa-trash"></i></button>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="{{ $multiBusiness ? 4 : 3 }}" class="text-right font-weight-bold">Invoice Total:</td>
                <td class="font-weight-bold text-right text-success" id="invoiceTotal">TZS 0</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addLineBtn"><i class="fa fa-plus"></i> Add Line</button>

        <div class="form-group">
          <label class="font-weight-bold">Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Payment terms, delivery notes, etc.">{{ old('notes') }}</textarea>
        </div>

        <div class="text-right mt-3">
          <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
            <i class="fa fa-save"></i> Create Invoice
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
  const catalogOptions = @json($catalogItems);
  const businessTypes = @json($businessTypes ?? []);
  const multiBusiness = @json($multiBusiness);
  let lineIndex = 1;

  function businessTypeOptionsHtml() {
    let html = '<option value="">Select business...</option>';
    businessTypes.forEach(function (type) {
      html += '<option value="' + type.key + '">' + type.label + '</option>';
    });
    return html;
  }

  function itemOptionsForType(typeKey) {
    let html = '<option value="">Select item...</option>';
    const items = typeKey
      ? catalogOptions.filter(function (item) { return String(item.business_type_key) === String(typeKey); })
      : catalogOptions;

    items.forEach(function (item) {
      html += '<option value="' + item.id + '" data-price="' + item.price + '" data-stock="' + item.stock + '">'
        + item.name + ' (' + item.sku + ') — TZS ' + Number(item.price).toLocaleString() + ' · Stock: ' + item.stock
        + '</option>';
    });

    return html;
  }

  function initItemSelect($select) {
    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }
    $select.select2({ width: '100%', placeholder: 'Select item...' });
  }

  function refreshRowItems($row, preserveSelection) {
    const typeKey = multiBusiness ? ($row.find('.line-business-type').val() || '') : '';
    const $itemSelect = $row.find('.item-select');
    const previous = preserveSelection ? $itemSelect.val() : '';

    $itemSelect.html(itemOptionsForType(typeKey));

    if (multiBusiness) {
      const enabled = !!typeKey;
      $itemSelect.prop('disabled', !enabled);
      $itemSelect.prop('required', enabled);
    }

    initItemSelect($itemSelect);

    if (previous && $itemSelect.find('option[value="' + previous + '"]').length) {
      $itemSelect.val(previous).trigger('change');
    } else {
      $itemSelect.val('').trigger('change');
      $row.find('.price-input').val('');
      $row.find('.line-total').text(formatMoney(0));
    }
  }

  function formatMoney(n) {
    return 'TZS ' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function recalcRow($row) {
    const qty = parseFloat($row.find('.qty-input').val()) || 0;
    const price = parseFloat($row.find('.price-input').val()) || 0;
    const total = qty * price;
    $row.find('.line-total').text(formatMoney(total));
    recalcInvoice();
  }

  function recalcInvoice() {
    let sum = 0;
    let valid = false;
    $('#linesBody .line-row').each(function () {
      const $row = $(this);
      const itemId = $row.find('.item-select').val();
      const qty = parseFloat($row.find('.qty-input').val()) || 0;
      const price = parseFloat($row.find('.price-input').val()) || 0;
      const businessOk = !multiBusiness || !!$row.find('.line-business-type').val();

      if (businessOk && itemId && qty > 0 && price >= 0) {
        valid = true;
        sum += qty * price;
      }
    });
    $('#invoiceTotal').text(formatMoney(sum));
    $('#submitBtn').prop('disabled', !valid);
  }

  function bindRow($row) {
    $row.find('.line-business-type').on('change', function () {
      refreshRowItems($row, false);
      recalcInvoice();
    });

    $row.find('.item-select').on('change', function () {
      const price = $(this).find(':selected').data('price');
      if (price !== undefined) {
        $row.find('.price-input').val(price);
      }
      recalcRow($row);
    });

    $row.find('.qty-input, .price-input').on('input', function () {
      recalcRow($row);
    });

    $row.find('.remove-line').on('click', function () {
      if ($('#linesBody .line-row').length > 1) {
        $row.remove();
        updateRemoveButtons();
        recalcInvoice();
      }
    });
  }

  function updateRemoveButtons() {
    const rows = $('#linesBody .line-row');
    rows.find('.remove-line').prop('disabled', rows.length <= 1);
  }

  $('#addLineBtn').on('click', function () {
    const idx = lineIndex++;
    let rowHtml = '<tr class="line-row">';

    if (multiBusiness) {
      rowHtml += '<td><select class="form-control line-business-type" required>' + businessTypeOptionsHtml() + '</select></td>';
      rowHtml += '<td><select name="items[' + idx + '][id]" class="form-control item-select" disabled><option value="">Select item...</option></select></td>';
    } else {
      rowHtml += '<td><select name="items[' + idx + '][id]" class="form-control item-select" required>' + itemOptionsForType('') + '</select></td>';
    }

    rowHtml += '<td><input type="number" name="items[' + idx + '][qty]" class="form-control qty-input" min="1" value="1" required></td>'
      + '<td><input type="number" name="items[' + idx + '][price]" class="form-control price-input" min="0" step="0.01" required></td>'
      + '<td class="line-total align-middle font-weight-bold text-right">' + formatMoney(0) + '</td>'
      + '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-danger remove-line"><i class="fa fa-trash"></i></button></td>'
      + '</tr>';

    const $row = $(rowHtml);
    $('#linesBody').append($row);
    initItemSelect($row.find('.item-select'));
    bindRow($row);
    updateRemoveButtons();
    recalcInvoice();
  });

  $('#customerSelect').select2({ width: '100%', placeholder: 'Select customer or walk-in' }).on('change', function () {
    const $opt = $(this).find(':selected');
    if ($opt.val()) {
      $('#customerName').val($opt.data('name') || '');
      $('#customerPhone').val(($opt.data('phone') || '').replace(/^\+255/, ''));
      $('#customerEmail').val($opt.data('email') || '');
    }
  });

  bindRow($('#linesBody .line-row'));
  initItemSelect($('#linesBody .line-row .item-select'));
  updateRemoveButtons();
  recalcInvoice();
})();
</script>
@endsection
