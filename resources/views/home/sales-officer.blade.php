<style>
    :root { --brand: #940000; --brand-dark: #6b0000; }
    .widget-small { height: 90px !important; border-radius: 8px !important; margin-bottom: 20px; overflow: hidden; transition: transform 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important; }
    .widget-small:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important; }
    .widget-small.coloured-icon { background-color: #fff !important; }
    .widget-small.coloured-icon .info { color: #000 !important; }
    .widget-small .icon { min-width: 70px !important; padding: 10px !important; font-size: 1.8rem !important; }
    .widget-small .info h4 { font-size: 0.75rem !important; margin-bottom: 2px !important; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #666; }
    .widget-small .info p { font-size: 16px !important; margin: 0 !important; font-weight: 700; }
    .smallest { font-size: 11px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .verify-filter-pill.active { background-color: var(--brand) !important; border-color: var(--brand) !important; color: #fff !important; }
    .verify-item-card { border-radius: 12px; border-top: 3px solid var(--brand) !important; transition: all 0.2s ease; }
    .verify-item-card:hover { box-shadow: 0 4px 12px rgba(148,0,0,0.1); }
</style>

@if($needsShift ?? false)
<div class="row mt-2">
  <div class="col-md-12">
    <div class="tile shadow-sm border-0" style="border-radius: 15px; border-left: 4px solid #940000 !important;">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 pb-3 border-bottom">
        <h3 class="tile-title mb-2 mb-md-0"><i class="fa fa-clock-o text-primary"></i> Physical Stock Verification & Open Shift</h3>
        <div class="d-flex align-items-center flex-wrap">
          <div class="btn-group mr-2 mb-2 shadow-sm">
            <button type="button" class="btn btn-light btn-sm verify-view-btn active" data-view="grid"><i class="fa fa-th"></i></button>
            <button type="button" class="btn btn-light btn-sm verify-view-btn" data-view="list"><i class="fa fa-list"></i></button>
          </div>
          <span class="badge badge-light border text-muted px-3 py-2 mb-2">
            <i class="fa fa-info-circle"></i> Verify stock, then open your shift
          </span>
        </div>
      </div>

      @php $canOpenNow = ($shiftOpenCheck['allowed'] ?? true); @endphp
      @if(! $canOpenNow)
        <div class="alert alert-warning mb-3">
          <i class="fa fa-clock-o"></i> <strong>Shift opening is not allowed right now.</strong>
          {{ $shiftOpenCheck['message'] ?? '' }}
          <div class="small mt-1 mb-0">Allowed window: {{ $shiftOpenWindowLabel ?? 'Any time' }}</div>
        </div>
      @else
        <div class="alert alert-light border small mb-3 py-2">
          <i class="fa fa-info-circle text-primary"></i> Shift opening window: <strong>{{ $shiftOpenWindowLabel ?? 'Any time' }}</strong>
        </div>
      @endif

      <div class="row mb-3">
        <div class="col-md-3">
          <label class="smallest font-weight-bold text-uppercase text-muted">Search Items</label>
          <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
            <input type="text" id="verifySearch" class="form-control" placeholder="Type to search...">
          </div>
        </div>
        <div class="col-md-9">
          <label class="smallest font-weight-bold text-uppercase text-muted">Quick Filters</label>
          <div class="d-flex align-items-center overflow-auto no-scrollbar py-1" id="verifyFilterContainer">
            <button class="btn btn-sm btn-outline-primary active verify-filter-pill mr-2 mb-1" data-filter="all">ALL ITEMS</button>
            @foreach($verifyCategories as $cat)
              <button class="btn btn-sm btn-outline-primary verify-filter-pill mr-2 mb-1 text-uppercase" data-filter="{{ Str::slug($cat) }}">{{ $cat }}</button>
            @endforeach
          </div>
        </div>
      </div>

      @if($verifyItems->isEmpty())
        <div class="alert alert-warning text-center py-4">
          <i class="fa fa-cubes fa-2x mb-2"></i>
          <p class="mb-0">No in-stock items to verify. Receive stock or assign categories to items first.</p>
        </div>
      @else
        <div id="verifyStockGrid" class="row mx-n2" style="max-height: 480px; overflow-y: auto;">
          @foreach($verifyItems as $item)
          <div class="col-xl-3 col-lg-4 col-md-4 col-6 px-2 mb-3 verify-item-wrapper"
               data-category="{{ $item['category_slug'] }}"
               data-name="{{ strtolower($item['name'] . ' ' . $item['category']) }}">
            <div class="p-2 bg-white rounded border shadow-xs h-100 verify-item-card {{ $item['is_low_stock'] ? 'bg-light' : '' }}">
              <h6 class="smallest font-weight-bold text-dark mb-1 text-truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</h6>
              <span class="smallest text-muted text-uppercase d-block mb-2">{{ $item['category'] }}</span>
              <div class="bg-light rounded p-2 text-center" style="border: 1px dashed #ddd;">
                <div class="smallest text-muted text-uppercase">System Stock</div>
                <div class="font-weight-bold text-success">{{ $item['formatted_quantity'] }}</div>
              </div>
            </div>
          </div>
          @endforeach
        </div>

        <div id="verifyStockList" class="table-responsive d-none" style="max-height: 480px; overflow-y: auto;">
          <table class="table table-hover table-bordered table-sm bg-white">
            <thead class="bg-light">
              <tr>
                <th>Item</th>
                <th>Category</th>
                <th class="text-center">System Stock</th>
              </tr>
            </thead>
            <tbody>
              @foreach($verifyItems as $item)
              <tr class="verify-item-wrapper"
                  data-category="{{ $item['category_slug'] }}"
                  data-name="{{ strtolower($item['name'] . ' ' . $item['category']) }}">
                <td class="font-weight-bold">{{ $item['name'] }}</td>
                <td><span class="badge badge-light border">{{ $item['category'] }}</span></td>
                <td class="text-center font-weight-bold text-success">{{ $item['formatted_quantity'] }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="row mt-4 pt-3 border-top">
          <div class="col-md-8">
            <p class="text-muted small mb-0">
              <i class="fa fa-info-circle"></i> Review the stock above, then continue to enter physical counts and open your shift.
            </p>
          </div>
          <div class="col-md-4 text-md-right mt-3 mt-md-0">
            @if($canOpenNow)
              <a href="{{ route('shifts.create') }}" class="btn btn-primary px-4 py-2 shadow-sm w-100 w-md-auto" style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-play mr-2"></i> VERIFY & OPEN SHIFT
              </a>
            @else
              <button type="button" class="btn btn-secondary px-4 py-2 shadow-sm w-100 w-md-auto" disabled style="border-radius: 10px; font-weight: 600;">
                <i class="fa fa-ban mr-2"></i> OPENING NOT ALLOWED NOW
              </button>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>
</div>

@include('home.partials.my-sales-targets')

@else
@php
  $shiftOverdue = $shiftOverdueStatus ?? null;
  $shiftBlocked = ($shiftOverdue['overdue'] ?? false) && ($shiftOverdue['enforced'] ?? false);
@endphp

@if($shiftOverdue && ($shiftOverdue['overdue'] ?? false))
<div class="row">
  <div class="col-md-12">
    <div class="alert alert-{{ $shiftBlocked ? 'danger' : 'warning' }} mb-3">
      <i class="fa fa-exclamation-triangle"></i>
      <strong>Shift open longer than allowed ({{ $shiftOverdue['duration_text'] ?? '' }}).</strong>
      {{ $shiftOverdue['message'] ?? '' }}
      @if($shiftOverdue['deadline'] ?? null)
        <div class="small mt-1 mb-0">Must close by: {{ $shiftOverdue['deadline']->format('d M Y, h:i A') }}</div>
      @endif
      <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="btn btn-sm btn-{{ $shiftBlocked ? 'danger' : 'warning' }} ml-0 ml-md-2 mt-2 mt-md-0">
        <i class="fa fa-power-off"></i> End Shift Now
      </a>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile p-3 mb-3 bg-light border-left border-primary shadow-sm" style="border-left-width: 4px !important;">
      @if(!empty($targetProgress) && $targetProgress->count() > 0)
        <div class="border-bottom pb-2 mb-2">
          @include('home.partials.my-sales-targets', ['goalsInline' => true])
        </div>
      @endif
      <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
          <h5 class="mb-0 text-dark"><i class="fa fa-user-circle text-primary"></i> Working as: <span class="font-weight-bold">{{ Auth::user()->name }}</span></h5>
          <small class="text-muted">
            <i class="fa fa-clock-o"></i> Shift #{{ $openShift->id }}
            (Opened {{ $openShift->opened_at->format('h:i A') }}) &nbsp;|&nbsp;
            <span id="shift-realtime-counter" data-opened-at="{{ $openShift->opened_at->toIso8601String() }}" class="badge badge-success">
              <i class="fa fa-clock-o"></i> <span id="shift-timer-text">00:00:00</span>
            </span>
          </small>
        </div>
        <div>
          <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="btn btn-outline-danger btn-sm font-weight-bold shadow-sm">
            <i class="fa fa-power-off"></i> END SHIFT
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-shopping-bag fa-3x"></i>
      <div class="info">
        <h4>Shift Sales</h4>
        <p>{{ number_format($shiftOrderCount ?? 0) }}</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Shift Collected</h4>
        <p>{{ money($shiftRevenue ?? 0) }}</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>In-Stock Items</h4>
        <p>{{ $stockItemsCount ?? 0 }}</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-bell fa-3x"></i>
      <div class="info">
        <h4>Pending Payment</h4>
        <p>{{ $pendingPayments ?? 0 }}</p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Quick Actions</h3>
      <div class="tile-body">
        <div class="row">
          <div class="col-md-3 col-sm-6 mb-3">
            @if($shiftBlocked ?? false)
              <button type="button" class="btn btn-secondary btn-block btn-lg py-3" disabled title="Close your shift first">
                <i class="fa fa-shopping-cart fa-2x d-block mb-1"></i>
                Place New Sale (POS)
              </button>
            @else
              <a href="{{ route('sales.create') }}" class="btn btn-primary btn-block btn-lg py-3">
                <i class="fa fa-shopping-cart fa-2x d-block mb-1"></i>
                Place New Sale (POS)
              </a>
            @endif
          </div>
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('sales.index') }}" class="btn btn-info btn-block btn-lg py-3">
              <i class="fa fa-list-alt fa-2x d-block mb-1"></i>
              My Sales
              @if(($pendingPayments ?? 0) > 0)
                <span class="badge badge-danger">{{ $pendingPayments }}</span>
              @endif
            </a>
          </div>
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('items.stock') }}" class="btn btn-success btn-block btn-lg py-3">
              <i class="fa fa-cubes fa-2x d-block mb-1"></i>
              Item Stock
            </a>
          </div>
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('shifts.index') }}" class="btn btn-dark btn-block btn-lg py-3">
              <i class="fa fa-history fa-2x d-block mb-1"></i>
              Shift History
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Recent Shift Sales</h3>
      <div class="table-responsive">
        <table class="table table-hover table-sm">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentSales ?? [] as $sale)
            <tr>
              <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference_no }}</a></td>
              <td>{{ $sale->customer->name ?? $sale->customer_name ?? 'Walk-in' }}</td>
              <td>{{ money($sale->total_amount) }}</td>
              <td>
                @if($sale->payment_status === 'paid')
                  <span class="badge badge-success">Paid</span>
                @elseif(in_array($sale->payment_status, ['pending', 'partial', 'debt']))
                  <span class="badge badge-warning">{{ ucfirst($sale->payment_status) }}</span>
                @else
                  <span class="badge badge-secondary">{{ ucfirst($sale->payment_status) }}</span>
                @endif
              </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted">No sales yet this shift.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Low Stock Alerts <small class="text-muted">(≤ {{ $lowStockThreshold }})</small></h3>
      <div class="table-responsive">
        <table class="table table-hover table-sm">
          <thead>
            <tr>
              <th>Item</th>
              <th class="text-center">Stock</th>
            </tr>
          </thead>
          <tbody>
            @forelse($lowStockItems ?? [] as $item)
            <tr>
              <td class="font-weight-bold">{{ $item->name }}</td>
              <td class="text-center"><span class="badge badge-danger">{{ fmod($item->current_stock, 1.0) === 0.0 ? (int) $item->current_stock : number_format($item->current_stock, 2) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="2" class="text-center text-muted">No low stock items.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

@push('scripts')
<script>
$(document).ready(function () {
    $('#verifySearch').on('keyup', function () {
        const val = $(this).val().toLowerCase();
        $('.verify-item-wrapper').each(function () {
            $(this).toggle(String($(this).data('name')).includes(val));
        });
    });

    $('.verify-filter-pill').on('click', function () {
        $('.verify-filter-pill').removeClass('active');
        $(this).addClass('active');
        const filter = $(this).data('filter');
        $('.verify-item-wrapper').each(function () {
            if (filter === 'all') {
                $(this).show();
            } else {
                $(this).toggle($(this).data('category') === filter);
            }
        });
    });

    $('.verify-view-btn').on('click', function () {
        $('.verify-view-btn').removeClass('active btn-primary text-white').addClass('btn-light');
        $(this).addClass('active btn-primary text-white').removeClass('btn-light');
        if ($(this).data('view') === 'grid') {
            $('#verifyStockGrid').removeClass('d-none');
            $('#verifyStockList').addClass('d-none');
        } else {
            $('#verifyStockGrid').addClass('d-none');
            $('#verifyStockList').removeClass('d-none');
        }
    });

    const timerEl = document.getElementById('shift-timer-text');
    const shiftCounterEl = document.getElementById('shift-realtime-counter');
    if (timerEl && shiftCounterEl) {
        const openedAt = new Date(shiftCounterEl.getAttribute('data-opened-at'));
        function updateTimer() {
            const diffMs = Date.now() - openedAt.getTime();
            if (diffMs > 0) {
                const hours = Math.floor(diffMs / 3600000);
                const minutes = Math.floor((diffMs % 3600000) / 60000);
                const seconds = Math.floor((diffMs % 60000) / 1000);
                timerEl.textContent =
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
            }
        }
        updateTimer();
        setInterval(updateTimer, 1000);
    }
});
</script>
@endpush
