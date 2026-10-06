@extends('layouts.app')

@section('title', 'Payment Queue')

@section('styles')
<style>
  .cashier-queue-table td { vertical-align: middle; }
  @keyframes queueNewFlash { 0% { background: #fff3cd; } 100% { background: transparent; } }
  .queue-row.queue-row-new td { animation: queueNewFlash 6s ease-out; }
  .queue-row.queue-row-new td:first-child { box-shadow: inset 4px 0 0 #ffc107; }
  @if(auth()->user()->isPaymentCashier())
  #payItemsTable th:nth-child(4), #payItemsTable td:nth-child(4),
  #payItemsTable th:nth-child(5), #payItemsTable td:nth-child(5),
  #payItemsTable th:nth-child(7), #payItemsTable td:nth-child(7) { display: none; }
  @endif
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Payment Queue</h1>
    <p>
      Orders placed by sales officers waiting for payment
      @if($branchName) — branch <strong>{{ $branchName }}</strong>@elseif($isOverseer) — <strong>all branches</strong>@endif
    </p>
  </div>
  <div>
    <button type="button" class="btn btn-outline-secondary" id="alertToggle" title="New order alerts (sound + notification)">
      <i class="fa fa-bell"></i> <span>Alerts on</span>
    </button>
    @if($isOverseer)
      <a href="{{ route('reports.cashiers') }}" class="btn btn-outline-info"><i class="fa fa-bar-chart"></i> Cashier Report</a>
    @endif
    @if($openShift)
      <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="btn btn-outline-primary">
        <i class="fa fa-handshake-o"></i> My Handover
      </a>
    @endif
  </div>
</div>

@if($reason = auth()->user()->paymentCollectionBlockedReason())
  <div class="alert alert-warning"><i class="fa fa-info-circle"></i> {{ $reason }}</div>
@endif

<div class="row mb-3">
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-list fa-3x"></i>
      <div class="info"><h4>Waiting</h4><p><b id="statOrders">{{ $stats['orders'] }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small danger coloured-icon"><i class="icon fa fa-hourglass-half fa-3x"></i>
      <div class="info"><h4>Total Due</h4><p><b id="statDue">{{ money($stats['total_due']) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-adjust fa-3x"></i>
      <div class="info"><h4>Partial</h4><p><b id="statPartial">{{ $stats['partial'] }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-check fa-3x"></i>
      <div class="info"><h4>I Collected</h4><p><b id="statCollected">{{ money($collections['total']) }}</b></p></div>
    </div>
  </div>
</div>

@if($isOverseer)
<div class="row">
  <div class="col-12">
    <div class="tile">
      <h3 class="tile-title mb-2" style="font-size: 1.05rem;"><i class="fa fa-users"></i> Cashiers on duty <small class="text-muted">· live</small></h3>
      <div id="onDutyWrap">@include('cashier.partials.on-duty', ['onDuty' => $onDuty])</div>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-12">
    <div class="tile">
      <ul class="nav nav-tabs mb-3" id="queueTabs">
        <li class="nav-item">
          <a class="nav-link {{ $tab === 'unpaid' ? 'active' : '' }}" href="#" data-tab="unpaid">
            <i class="fa fa-hourglass-half"></i> Unpaid <span class="badge badge-danger" id="tabUnpaidCount">{{ $stats['orders'] }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $tab === 'paid' ? 'active' : '' }}" href="#" data-tab="paid">
            <i class="fa fa-check"></i> Paid <span class="badge badge-success" id="tabPaidCount">{{ $paidCount }}</span>
          </a>
        </li>
      </ul>

      <form method="GET" action="{{ route('cashier.queue') }}" class="form-inline mb-3" id="queueFilterForm" onsubmit="return false;">
        <input type="hidden" name="tab" id="queueTab" value="{{ $tab }}">
        <div class="input-group input-group-sm mr-2 mb-2" style="min-width: 280px;">
          <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
          <input type="text" name="q" id="queueSearch" value="{{ $search }}" class="form-control" placeholder="Search order no, customer, phone or officer..." autocomplete="off"
                 data-typewriter='["order number","customer name","phone number","sales officer"]'>
        </div>
        <select name="status" id="queueStatus" class="form-control form-control-sm mr-2 mb-2 {{ $tab === 'paid' ? 'd-none' : '' }}">
          <option value="">All unpaid</option>
          <option value="pending" @selected($status === 'pending')>Awaiting payment</option>
          <option value="partial" @selected($status === 'partial')>Partial</option>
        </select>
        <input type="date" name="date" id="queueDate" value="{{ request('date', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
               class="form-control form-control-sm mr-2 mb-2 {{ $tab === 'paid' ? '' : 'd-none' }}" title="Paid on">
        @if($isOverseer && $branches->count() > 1)
          <select name="branch_id" id="queueBranch" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All branches</option>
            @foreach($branches as $branch)
              <option value="{{ $branch->id }}" @selected((int) $viewerBranchId === (int) $branch->id)>{{ $branch->name }}</option>
            @endforeach
          </select>
        @endif
        <span class="small text-muted mb-2" id="queueLoading" style="display:none;"><i class="fa fa-spinner fa-spin"></i> Searching...</span>
        <span class="small text-muted ml-auto mb-2"><i class="fa fa-refresh"></i> Live · refreshes every 10s</span>
      </form>

      <div class="table-responsive" id="queueTableWrap">
        @include('cashier.partials.queue-table', ['sales' => $sales, 'tab' => $tab])
      </div>
      <div class="mt-3" id="queuePagination">{{ $sales->links('pagination::bootstrap-4') }}</div>
    </div>
  </div>

</div>

@include('sales.partials.payment-modal')

@if($receipt)
<div class="modal fade" id="receiptShareModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa fa-check-circle"></i> Payment recorded — {{ $receipt['reference_no'] }}</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">
          {{ $receipt['customer_name'] ?: 'Walk-in customer' }}
          @if($receipt['customer_phone'])<span class="text-muted">· {{ $receipt['customer_phone'] }}</span>@endif
        </p>
        <table class="table table-sm mb-3">
          <tr><th>Total</th><td class="text-right">{{ money($receipt['total_amount']) }}</td></tr>
          <tr><th>Paid</th><td class="text-right text-success">{{ money($receipt['amount_paid']) }}</td></tr>
          @if($receipt['balance'] > 0)
            <tr><th>Balance</th><td class="text-right text-danger">{{ money($receipt['balance']) }}</td></tr>
          @endif
        </table>
        <div class="d-flex flex-wrap" style="gap: 6px;">
          <a href="{{ $receipt['print_url'] }}" target="_blank" class="btn btn-primary"><i class="fa fa-print"></i> Print</a>
          <a href="{{ $receipt['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-success"><i class="fa fa-whatsapp"></i> WhatsApp</a>
          @if($receipt['sms_url'])
            <a href="{{ $receipt['sms_url'] }}" class="btn btn-info"><i class="fa fa-commenting"></i> SMS</a>
          @endif
          <button type="button" class="btn btn-outline-secondary" id="receiptCopyBtn" data-text="{{ $receipt['text'] }}"><i class="fa fa-copy"></i> Copy</button>
        </div>
        @unless($receipt['customer_phone'])
          <p class="small text-muted mt-2 mb-0">No customer phone on this order — WhatsApp will ask you to pick a contact.</p>
        @endunless
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Next customer</button>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('scripts')
  @include('sales.partials.customer-picker-scripts')
  @include('sales.partials.payment-modal-scripts')
  <script type="text/javascript">
    $(function () {
      @if(auth()->user()->isPaymentCashier())
      $('#payItemsTable').closest('.table-responsive').prev('p').text('Collect the amount due. Price changes are done by the sales officer.');
      @endif

      const baseUrl = @json(route('cashier.queue'));
      let currentPage = 1;
      let activeRequest = null;
      let searchTimer = null;

      function buildParams() {
        const tab = $('#queueTab').val();
        const params = { tab: tab };
        const q = ($('#queueSearch').val() || '').trim();
        if (q) params.q = q;
        if (tab === 'paid') {
          if ($('#queueDate').val()) params.date = $('#queueDate').val();
        } else if ($('#queueStatus').val()) {
          params.status = $('#queueStatus').val();
        }
        if ($('#queueBranch').length && $('#queueBranch').val()) params.branch_id = $('#queueBranch').val();
        if (currentPage > 1) params.page = currentPage;
        return params;
      }

      const csrf = @json(csrf_token());
      const lockUrl = @json(url('/cashier/orders')) + '/';
      let lastSeenId = @json((int) $latestId);
      let lockedSaleId = null;
      let lockHeartbeat = null;
      let alertsOn = localStorage.getItem('cashierQueueAlerts') !== 'off';
      let audioCtx = null;

      function renderAlertToggle() {
        $('#alertToggle').toggleClass('btn-outline-secondary', !alertsOn).toggleClass('btn-warning', alertsOn)
          .find('span').text(alertsOn ? 'Alerts on' : 'Alerts off');
        $('#alertToggle i').toggleClass('fa-bell', alertsOn).toggleClass('fa-bell-slash', !alertsOn);
      }

      function beep() {
        try {
          audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
          [0, 0.25].forEach(function (offset) {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime + offset);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + offset + 0.2);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + offset);
            osc.stop(audioCtx.currentTime + offset + 0.2);
          });
        } catch (e) {}
      }

      function announceNewOrders(newIds) {
        newIds.forEach(function (id) {
          $('#queueTableWrap tr.queue-row[data-sale-id="' + id + '"]').addClass('queue-row-new');
        });
        if (!alertsOn) return;
        beep();
        const text = newIds.length > 1 ? newIds.length + ' new orders waiting for payment' : 'New order waiting for payment';
        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'info', title: text });
        if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
          try { new Notification('Payment Queue', { body: text, tag: 'cashier-queue' }); } catch (e) {}
        }
      }

      $('#alertToggle').on('click', function () {
        alertsOn = !alertsOn;
        localStorage.setItem('cashierQueueAlerts', alertsOn ? 'on' : 'off');
        renderAlertToggle();
        if (alertsOn) {
          beep();
          if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
        }
      });
      renderAlertToggle();
      $(document).one('click', function () {
        if (alertsOn && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission();
      });

      function releaseLock(useBeacon) {
        if (!lockedSaleId) return;
        const id = lockedSaleId;
        lockedSaleId = null;
        clearInterval(lockHeartbeat);
        if (useBeacon) {
          fetch(lockUrl + id + '/lock', { method: 'DELETE', keepalive: true, headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
        } else {
          $.ajax({ url: lockUrl + id + '/lock', type: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
        }
      }

      let pendingLockSaleId = null;

      $('#queueTableWrap').on('click', '.open-payment-modal-btn', function () {
        const saleId = $(this).data('sale-id');
        if (lockedSaleId && lockedSaleId !== saleId) releaseLock(false);
        pendingLockSaleId = saleId;
        $.ajax({ url: lockUrl + saleId + '/lock', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, dataType: 'json' })
          .done(function () {
            if (pendingLockSaleId !== saleId) {
              $.ajax({ url: lockUrl + saleId + '/lock', type: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
              return;
            }
            pendingLockSaleId = null;
            lockedSaleId = saleId;
            clearInterval(lockHeartbeat);
            lockHeartbeat = setInterval(function () {
              if (lockedSaleId) $.ajax({ url: lockUrl + lockedSaleId + '/lock', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
            }, 30000);
          })
          .fail(function (xhr) {
            if (pendingLockSaleId !== saleId) return;
            pendingLockSaleId = null;
            if (xhr.status !== 409 && xhr.status !== 403) return;
            $('#paymentModal').modal('hide');
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'This order is being collected by another cashier.';
            if (typeof Toast !== 'undefined') Toast.fire({ icon: 'warning', title: msg });
            loadQueue(true);
          });
      });

      $('#paymentModal').on('hidden.bs.modal', function () {
        pendingLockSaleId = null;
        if (!$('#paymentModal').data('submitting')) releaseLock(false);
      });
      $('#paymentModal').on('submit', 'form', function (e) {
        if (e.isDefaultPrevented()) return;
        $('#paymentModal').data('submitting', true);
      });
      window.addEventListener('pagehide', function () {
        if (!$('#paymentModal').data('submitting')) releaseLock(true);
      });

      @if($receipt)
      $('#receiptShareModal').modal('show');
      $('#receiptCopyBtn').on('click', function () {
        const text = $(this).data('text');
        const done = function () { if (typeof Toast !== 'undefined') Toast.fire({ icon: 'success', title: 'Receipt copied' }); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
        else { const t = $('<textarea>').val(text).appendTo('body').select(); document.execCommand('copy'); t.remove(); done(); }
      });
      @endif

      function loadQueue(silent) {
        if (silent && $('#paymentModal').hasClass('show')) {
          return;
        }
        const params = buildParams();
        const query = $.param(params);
        if (activeRequest) activeRequest.abort();
        if (!silent) $('#queueLoading').show();

        activeRequest = $.ajax({
          url: baseUrl + '?' + query,
          dataType: 'json',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).done(function (res) {
          $('#queueTableWrap').html(res.html);
          $('#queuePagination').html(res.pagination);
          $('#statOrders, #tabUnpaidCount').text(res.stats.orders);
          $('#statPartial').text(res.stats.partial);
          $('#statDue').text('TZS ' + Math.round(res.stats.total_due).toLocaleString());
          $('#statCollected').text(res.collections_total);
          $('#tabPaidCount').text(res.paid_count);
          if (res.on_duty_html !== null && res.on_duty_html !== undefined) $('#onDutyWrap').html(res.on_duty_html);
          if (res.latest_id > lastSeenId) {
            const newIds = [];
            $('#queueTableWrap tr.queue-row').each(function () {
              const id = parseInt($(this).data('sale-id'), 10);
              if (id > lastSeenId) newIds.push(id);
            });
            announceNewOrders(newIds.length ? newIds : [res.latest_id]);
            lastSeenId = res.latest_id;
          }
          window.history.replaceState(null, '', baseUrl + '?' + query);
        }).always(function () {
          activeRequest = null;
          $('#queueLoading').hide();
        });
      }

      $('#queueSearch').on('input', function () {
        clearTimeout(searchTimer);
        currentPage = 1;
        searchTimer = setTimeout(function () { loadQueue(false); }, 300);
      });

      $('#queueStatus, #queueDate, #queueBranch').on('change', function () {
        currentPage = 1;
        loadQueue(false);
      });

      $('#queueTabs').on('click', '.nav-link', function (e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        $('#queueTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        $('#queueTab').val(tab);
        $('#queueStatus').toggleClass('d-none', tab === 'paid');
        $('#queueDate').toggleClass('d-none', tab !== 'paid');
        currentPage = 1;
        loadQueue(false);
      });

      $('#queuePagination').on('click', 'a.page-link', function (e) {
        e.preventDefault();
        const page = new URL($(this).attr('href'), window.location.origin).searchParams.get('page');
        currentPage = parseInt(page || '1', 10);
        loadQueue(false);
      });

      setInterval(function () { loadQueue(true); }, 10000);
    });
  </script>
@endsection
