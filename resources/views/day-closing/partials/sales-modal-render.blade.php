<script>
function dayClosingEscapeHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function dayClosingPaymentBadge(method, provider) {
  if (method === 'cash') return '<span class="badge badge-warning">CASH</span>';
  const label = (provider || (method === 'bank' ? 'BANK' : 'MOBILE')).toUpperCase();
  return `<span class="badge badge-success">${dayClosingEscapeHtml(label)}</span>`;
}

function dayClosingDigitalRefCell(payments, saleTxnRef) {
  const refs = [];
  (payments || []).forEach(p => {
    if (p.method !== 'cash' && p.reference) {
      refs.push(p.reference);
    }
  });
  if (!refs.length && saleTxnRef) {
    refs.push(saleTxnRef);
  }
  if (!refs.length) {
    return '-';
  }
  return refs.map(r => `<span class="small text-monospace">${dayClosingEscapeHtml(r)}</span>`).join('<br>');
}

function renderDayClosingSalesModal(sales, title) {
  if (!sales.length) {
    jQuery('#sales-content').html('<div class="alert alert-info">No sales found for this shift.</div>');
  } else {
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr><th>Ref</th><th>{{ __('tables.columns.time') }}</th><th>{{ __('tables.columns.cashier') }}</th><th>{{ __('tables.columns.total') }}</th><th>{{ __('tables.columns.paid') }}</th><th>Balance</th><th>This Shift</th><th>Payment</th><th>Payment Ref</th><th>{{ __('tables.columns.status') }}</th></tr></thead><tbody>';
    sales.forEach(s => {
      let payHtml = '-';
      if (s.payments && s.payments.length) {
        payHtml = s.payments.map(p => {
          let block = dayClosingPaymentBadge(p.method, p.provider);
          block += `<div class="small mt-1"><strong>TZS ${Number(p.amount).toLocaleString()}</strong>`;
          if (p.time) {
            block += `<div class="text-muted">${dayClosingEscapeHtml(p.time)}</div>`;
          }
          block += '</div>';
          return block;
        }).join('<hr class="my-1">');
      }
      const refCell = dayClosingDigitalRefCell(s.payments, s.sale_txn_ref);
      const refLabel = s.carried_over
        ? `${dayClosingEscapeHtml(s.ref)} <span class="badge badge-warning">Shift #${s.origin_shift_id}</span>`
        : dayClosingEscapeHtml(s.ref);
      const shiftCollected = s.carried_over && s.shift_collected != null
        ? `<span class="text-success font-weight-bold">TZS ${Number(s.shift_collected).toLocaleString()}</span>`
        : '-';
      html += `<tr>
        <td><strong>${refLabel}</strong></td>
        <td>${dayClosingEscapeHtml(s.time)}</td>
        <td>${dayClosingEscapeHtml(s.cashier)}</td>
        <td>TZS ${Number(s.total).toLocaleString()}</td>
        <td>TZS ${Number(s.paid).toLocaleString()}</td>
        <td>${s.balance > 0 ? '<span class="text-danger">TZS ' + Number(s.balance).toLocaleString() + '</span>' : '-'}</td>
        <td>${shiftCollected}</td>
        <td>${payHtml}</td>
        <td>${refCell}</td>
        <td><span class="badge badge-${s.status === 'paid' ? 'success' : 'warning'}">${dayClosingEscapeHtml(s.status)}</span></td>
      </tr>`;
    });
    html += '</tbody></table></div>';
    jQuery('#sales-content').html(html);
  }
  jQuery('#salesModal .modal-title').text(title || 'All Sales');
  jQuery('#salesModal').modal('show');
}
</script>
