@extends('layouts.app')

@section('title', 'Create Service Invoice')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-file-text-o"></i> Create Service Invoice</h1>
    <p>Customer receives SMS and PDF by email when phone/email are provided</p>
  </div>
  <a href="{{ route('service-invoices.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<div class="tile">
  <form method="POST" action="{{ route('service-invoices.store') }}" id="serviceInvoiceForm">
    @csrf
    <div class="row mb-3">
      <div class="col-md-3">
        <label class="font-weight-bold">Invoice date</label>
        <input type="date" name="sale_date" class="form-control" value="{{ date('Y-m-d') }}" required>
      </div>
      <div class="col-md-9">
        <label class="font-weight-bold">Customer</label>
        <select name="customer_id" id="customerSelect" class="form-control">
          <option value="">Walk-in</option>
          @foreach($customers as $c)
          <option value="{{ $c->id }}" data-name="{{ e($c->name) }}" data-phone="{{ e($c->phone ?? '') }}" data-email="{{ e($c->email ?? '') }}">{{ $c->name }}</option>
          @endforeach
        </select>
        <div class="row mt-2">
          <div class="col-md-4"><input class="form-control" name="customer_name" id="customerName" placeholder="Name"></div>
          <div class="col-md-4"><input class="form-control" name="customer_phone" id="customerPhone" placeholder="Phone for SMS"></div>
          <div class="col-md-4"><input type="email" class="form-control" name="customer_email" id="customerEmail" placeholder="Email for PDF"></div>
        </div>
      </div>
    </div>

    <h5>Service lines</h5>
    <table class="table table-bordered" id="linesTable">
      <thead class="thead-light">
        <tr><th style="width:45%">Service</th><th>Qty</th><th>Unit price</th><th>Line total</th><th></th></tr>
      </thead>
      <tbody id="linesBody">
        <tr class="line-row">
          <td>
            <select name="lines[0][service_id]" class="form-control service-select" required>
              <option value="">Select service</option>
              @foreach($services as $svc)
              <option value="{{ $svc->id }}" data-price="{{ (float)$svc->price }}" data-unit="{{ e($svc->unit_label) }}">
                {{ $svc->name }} ({{ $svc->category?->name }}) — {{ number_format((float)$svc->price, 0) }} / {{ $svc->unit_label }}
              </option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="lines[0][qty]" class="form-control qty-input" value="1" min="1" required></td>
          <td><input type="number" name="lines[0][price]" class="form-control price-input" step="1" min="0" required></td>
          <td class="line-total align-middle font-weight-bold">0</td>
          <td><button type="button" class="btn btn-sm btn-danger remove-line" disabled>&times;</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addLine"><i class="fa fa-plus"></i> Add line</button>

    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    <div class="text-right">
      <h5>Total: <span id="invoiceTotal">TZS 0</span></h5>
      <button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-check"></i> Create & send notifications</button>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const servicesOptions = @json($services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'category' => $s->category?->name, 'price' => (float)$s->price, 'unit' => $s->unit_label]));
  let lineIndex = 1;

  function serviceSelectHtml(idx) {
    let html = '<select name="lines[' + idx + '][service_id]" class="form-control service-select" required><option value="">Select service</option>';
    servicesOptions.forEach(function (s) {
      html += '<option value="' + s.id + '" data-price="' + s.price + '">' + s.name + ' (' + (s.category || '') + ') — ' + s.price.toLocaleString() + ' / ' + s.unit + '</option>';
    });
    return html + '</select>';
  }

  function recalc() {
    let total = 0;
    document.querySelectorAll('#linesBody .line-row').forEach(function (row) {
      const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
      const price = parseFloat(row.querySelector('.price-input').value) || 0;
      const lineTotal = qty * price;
      row.querySelector('.line-total').textContent = lineTotal.toLocaleString();
      total += lineTotal;
    });
    document.getElementById('invoiceTotal').textContent = 'TZS ' + total.toLocaleString();
  }

  function bindRow(row) {
    const sel = row.querySelector('.service-select');
    sel.addEventListener('change', function () {
      const opt = sel.options[sel.selectedIndex];
      if (opt && opt.dataset.price) {
        row.querySelector('.price-input').value = opt.dataset.price;
      }
      recalc();
    });
    row.querySelector('.qty-input').addEventListener('input', recalc);
    row.querySelector('.price-input').addEventListener('input', recalc);
    row.querySelector('.remove-line').addEventListener('click', function () {
      if (document.querySelectorAll('.line-row').length > 1) row.remove();
      recalc();
    });
  }

  document.getElementById('addLine').addEventListener('click', function () {
    const tr = document.createElement('tr');
    tr.className = 'line-row';
    tr.innerHTML = '<td>' + serviceSelectHtml(lineIndex) + '</td>' +
      '<td><input type="number" name="lines[' + lineIndex + '][qty]" class="form-control qty-input" value="1" min="1" required></td>' +
      '<td><input type="number" name="lines[' + lineIndex + '][price]" class="form-control price-input" step="1" min="0" required></td>' +
      '<td class="line-total align-middle font-weight-bold">0</td>' +
      '<td><button type="button" class="btn btn-sm btn-danger remove-line">&times;</button></td>';
    document.getElementById('linesBody').appendChild(tr);
    bindRow(tr);
    lineIndex++;
    recalc();
  });

  document.querySelectorAll('#linesBody .line-row').forEach(bindRow);
  recalc();

  $('#customerSelect').on('change', function () {
    const $o = $(this).find(':selected');
    if ($o.val()) {
      $('#customerName').val($o.data('name') || '');
      $('#customerPhone').val(($o.data('phone') || '').replace(/^\+255/, ''));
      $('#customerEmail').val($o.data('email') || '');
    }
  });
})();
</script>
@endsection
