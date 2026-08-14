@extends('layouts.app')

@section('title', __('pages.sales.title') . ' - SpareParts POS')

@section('styles')
<style>
  #salesTable td.sold-items-cell {
    max-width: 260px;
    white-space: normal;
    font-size: 0.9rem;
    line-height: 1.35;
  }
  .sales-page .sold-items-preview {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
  .sales-page .widget-small { min-height: 88px; border-radius: 8px !important; margin-bottom: 12px; }
  .sales-page .widget-small .icon { min-width: 64px !important; padding: 10px !important; font-size: 2rem !important; }
  .sales-page .widget-small .info h4 { font-size: 0.82rem !important; }
  .sales-page .widget-small .info p { font-size: 15px !important; word-break: break-word; }
  .sales-page .sales-title-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
  .sales-page .sales-mobile-card {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
  }
  .sales-page .sales-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
  .sales-page .sales-mobile-ref { font-weight: 700; color: #940000; font-size: 0.9rem; line-height: 1.35; }
  .sales-page .sales-mobile-meta { font-size: 0.82rem; color: #6c757d; margin-top: 2px; }
  .sales-page .sales-mobile-items {
    font-size: 0.88rem;
    line-height: 1.4;
    margin-bottom: 8px;
    word-break: break-word;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }
  .sales-page .sales-mobile-payment { margin-bottom: 10px; line-height: 1.4; }
  .sales-page .sales-mobile-actions { display: flex; flex-wrap: wrap; gap: 6px; padding-top: 8px; border-top: 1px solid #eee; }

  @media (max-width: 991.98px) {
    .sales-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .sales-page .app-title p { font-size: 0.88rem; }
    .sales-page .business-type-tabs { padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
  }

  @media (max-width: 767.98px) {
    .sales-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .sales-page .app-title h1 { font-size: 1.15rem; }
    .sales-page .app-title p { font-size: 0.82rem; }
    .sales-page .sales-title-actions { width: 100%; }
    .sales-page .sales-title-actions .btn { flex: 1 1 100%; text-align: center; }
    .sales-page .widget-small .icon { min-width: 52px !important; font-size: 1.5rem !important; }
  }
</style>
@endsection

@section('content')
<div class="sales-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-shopping-cart"></i> {{ __('pages.sales.title') }}</h1>
    <p>
      @if($showAllHistory ?? false)
        Showing all your past sales — products and services
      @elseif(($shiftContext ?? '') === 'current')
        Current shift #{{ $openShift->id }} sales (products &amp; services), plus any unpaid orders from earlier shifts
      @elseif(($shiftContext ?? '') === 'none')
        Open a shift to record new sales — previous shift sales are in Shift History
      @elseif($scopedToSelf ?? false)
        Your sales activity only (products &amp; services)
      @else
        All sales — products and services
      @endif
    </p>
    <div class="sales-title-actions d-print-none">
      @if(($requiresOpenShift ?? false) && !($openShift ?? null))
        <a href="{{ route('shifts.create') }}" class="btn btn-warning btn-sm"><i class="fa fa-clock-o"></i> {{ __('pages.sales.open_shift_first') }}</a>
      @else
        <a href="{{ route('sales.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> {{ __('pages.sales.new_sale') }}</a>
      @endif

      @if($requiresOpenShift ?? false)
        @if($showAllHistory ?? false)
          @if($openShift ?? null)
            <a href="{{ route('sales.index', ['history' => 'current']) }}" class="btn btn-info btn-sm"><i class="fa fa-calendar-check-o"></i> View Current Shift</a>
          @endif
        @else
          <a href="{{ route('sales.index', ['history' => 'all']) }}" class="btn btn-secondary btn-sm"><i class="fa fa-history"></i> View All My Past Sales</a>
        @endif
      @endif

      @php
        $filtersActive = request('period') || request('date_from') || request('date_to') || request('search') || request('q') || request('status') || request('payment_method');
      @endphp
      <button type="button" class="btn btn-sm {{ $filtersActive ? 'btn-info' : 'btn-outline-info' }}" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $filtersActive ? 'true' : 'false' }}" aria-controls="filterCollapse">
        <i class="fa fa-filter"></i> Filters
      </button>
    </div>
  </div>
</div>

@if(($requiresOpenShift ?? false) && !($openShift ?? null))
<div class="alert alert-warning">
  <i class="fa fa-exclamation-triangle"></i> You must <strong>open a shift</strong> with a physical stock check before using the POS.
  <a href="{{ route('shifts.create') }}" class="alert-link">Open shift now</a>
  · <a href="{{ route('shifts.index') }}" class="alert-link">View past shifts</a>
</div>
@elseif($openShift ?? false)
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-clock-o"></i> Shift #{{ $openShift->id }} is open.
  <a href="{{ route('shifts.show', $openShift) }}" class="alert-link">View</a>
</div>
@endif

@if(($carriedOverUnpaidCount ?? 0) > 0)
<div class="alert alert-warning py-2 mb-3">
  <i class="fa fa-exclamation-circle"></i>
  <strong>{{ $carriedOverUnpaidCount }}</strong> unpaid order{{ $carriedOverUnpaidCount === 1 ? '' : 's' }} from a previous shift still need payment — marked in the list with a shift badge.
</div>
@endif

@if($multiBusiness ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>Multi-department shop:</strong> use the tabs below to filter sales by business type.
</div>
@endif

<div class="row mb-3">
  <div class="col-6 col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.sales.total_sales') }}</h4>
        <p><b>{{ number_format($stats['total_sales']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.sales.gross_sales') }}</h4>
        <p><b>TZS {{ number_format($stats['gross_sales'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.sales.collected') }}</h4>
        <p><b>TZS {{ number_format($stats['collected'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.sales.outstanding') }}</h4>
        <p><b>TZS {{ number_format($stats['outstanding'], 0) }}</b></p>
      </div>
    </div>
  </div>
</div>

<!-- Date, Search, and Status Filters -->
<div class="row d-print-none collapse {{ $filtersActive ? 'show' : '' }} mb-3" id="filterCollapse">
  <div class="col-md-12">
    <div class="tile p-3">
      <form action="{{ route('sales.index') }}" method="GET" id="salesFilterForm" class="row align-items-end mb-0">
        @if(request('history'))
          <input type="hidden" name="history" value="{{ request('history') }}">
        @endif
        <input type="hidden" name="source" id="filterSource" value="{{ $saleSourceFilter ?? 'all' }}">

        <div class="col-md-4 col-lg-3 form-group mb-2">
          <label class="font-weight-bold"><i class="fa fa-search"></i> Search</label>
          <input type="search" name="search" id="filterSearch" class="form-control form-control-sm" placeholder="Ref #, customer, item…" value="{{ $search ?? request('search', request('q')) }}">
        </div>

        <div class="col-md-4 col-lg-2 form-group mb-2">
          <label class="font-weight-bold"><i class="fa fa-calendar"></i> Period</label>
          <select name="period" id="filterPeriod" class="form-control form-control-sm">
            <option value="">All dates</option>
            <option value="today" {{ (request('period') ?? $period ?? '') === 'today' ? 'selected' : '' }}>Today</option>
            <option value="this_week" {{ (request('period') ?? $period ?? '') === 'this_week' ? 'selected' : '' }}>This week</option>
            <option value="this_month" {{ (request('period') ?? $period ?? '') === 'this_month' ? 'selected' : '' }}>This month</option>
            <option value="custom" {{ (request('period') ?? $period ?? '') === 'custom' ? 'selected' : '' }}>Custom dates</option>
          </select>
        </div>

        <div class="col-md-4 col-lg-2 form-group mb-2 js-custom-dates {{ (request('period') ?? $period ?? '') === 'custom' ? '' : 'd-none' }}">
          <label class="font-weight-bold">From</label>
          <input type="date" name="date_from" id="filterDateFrom" class="form-control form-control-sm" value="{{ (request('period') ?? '') === 'custom' ? request('date_from') : '' }}">
        </div>

        <div class="col-md-4 col-lg-2 form-group mb-2 js-custom-dates {{ (request('period') ?? $period ?? '') === 'custom' ? '' : 'd-none' }}">
          <label class="font-weight-bold">To</label>
          <input type="date" name="date_to" id="filterDateTo" class="form-control form-control-sm" value="{{ (request('period') ?? '') === 'custom' ? request('date_to') : '' }}">
        </div>

        <div class="col-md-4 col-lg-2 form-group mb-2">
          <label class="font-weight-bold"><i class="fa fa-check-circle"></i> Status</label>
          <select name="status" id="filterStatus" class="form-control form-control-sm">
            <option value="all" {{ ($status ?? request('status')) === 'all' || !($status ?? request('status')) ? 'selected' : '' }}>All</option>
            <option value="paid" {{ ($status ?? request('status')) === 'paid' ? 'selected' : '' }}>Paid</option>
            <option value="partial" {{ ($status ?? request('status')) === 'partial' ? 'selected' : '' }}>Partial</option>
            <option value="debt" {{ ($status ?? request('status')) === 'debt' ? 'selected' : '' }}>Debt</option>
            <option value="pending" {{ ($status ?? request('status')) === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="cancelled" {{ ($status ?? request('status')) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>

        <div class="col-md-8 col-lg-3 form-group mb-2 d-flex align-items-end" style="gap: 8px;">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
          <a href="{{ route('sales.index', array_filter(['history' => request('history'), 'source' => $saleSourceFilter ?? request('source')])) }}" class="btn btn-secondary btn-sm"><i class="fa fa-refresh"></i> Clear</a>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      @php
        $saleSourceFilter = $saleSourceFilter ?? 'all';
        $sourceQuery = request()->except('source', 'page', 'cashier_id', 'user_id');
      @endphp
      <div class="business-type-tabs mb-3 px-3 pt-3" id="saleSourceTabs">
        <a href="{{ route('sales.index', $sourceQuery + ['source' => 'all']) }}"
           class="business-type-tab text-decoration-none {{ $saleSourceFilter === 'all' ? 'active' : '' }}">
          <i class="fa fa-th-large"></i> All
        </a>
        <a href="{{ route('sales.index', $sourceQuery + ['source' => 'products']) }}"
           class="business-type-tab text-decoration-none {{ $saleSourceFilter === 'products' ? 'active' : '' }}">
          <i class="fa fa-cube"></i> Products
        </a>
        <a href="{{ route('sales.index', $sourceQuery + ['source' => 'services']) }}"
           class="business-type-tab text-decoration-none {{ $saleSourceFilter === 'services' ? 'active' : '' }}">
          <i class="fa fa-briefcase"></i> Services
        </a>
      </div>
      @if($multiBusiness ?? false)
      <div class="business-type-tabs mb-3 px-3" id="businessTypeTabs">
        <button type="button" class="business-type-tab active" data-business-type="all">
          <i class="fa fa-th-large"></i> All
        </button>
        @foreach($businessTypes as $type)
        <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
          <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
        </button>
        @endforeach
      </div>
      @endif
      <div class="tile-body">
        <div class="d-lg-none mb-3" id="salesMobileList">
          @include('sales.partials.sale-mobile-list', ['sales' => $sales, 'shiftContext' => $shiftContext ?? '', 'openShift' => $openShift ?? null])
        </div>
        <div class="sales-desktop-table d-none d-lg-block">
        <table class="table table-hover table-bordered" id="salesTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.reference_no') }}</th>
              <th>{{ __('tables.columns.items_sold') }}</th>
              <th>{{ __('tables.columns.cashier') }}</th>
              <th>{{ __('tables.columns.total_amount') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>{{ __('tables.columns.payment_details') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @include('sales.partials.sale-table-rows', ['sales' => $sales, 'openShift' => $openShift ?? null, 'shiftContext' => $shiftContext ?? '', 'showAllHistory' => $showAllHistory ?? false])
          </tbody>
        </table>
        </div>
        <div class="d-flex justify-content-center mt-3" id="salesPaginationContainer">
          {{ $sales->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>
      </div>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
</div>
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            const hasMultipleBusinessTypes = @json($multiBusiness ?? false);
            let activeBusinessType = 'all';
            let table = null;

            function filterMobileSalesCards() {
                let visible = 0;
                $('.sales-mobile-card').each(function () {
                    const keys = String($(this).data('business-types') || '').split(',').filter(Boolean);
                    const show = activeBusinessType === 'all' || keys.indexOf(String(activeBusinessType)) !== -1;
                    $(this).toggle(show);
                    if (show) {
                        visible++;
                    }
                });
                $('#salesMobileNoMatch').toggleClass('d-none', visible > 0 || $('.sales-mobile-card').length === 0);
            }

            table = $('#salesTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, type: 'num' },
                ],
            });
            $(table.table().container()).addClass('sales-datatable-wrap');
            if (hasMultipleBusinessTypes) {
                filterMobileSalesCards();
            }

            if (hasMultipleBusinessTypes) {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (settings.nTable.id !== 'salesTable') {
                        return true;
                    }

                    if (activeBusinessType === 'all') {
                        return true;
                    }

                    const row = table.row(dataIndex).node();
                    const keys = String($(row).attr('data-business-types') || '').split(',').filter(Boolean);

                    return keys.indexOf(String(activeBusinessType)) !== -1;
                });

                $('#businessTypeTabs .business-type-tab').on('click', function () {
                    $('#businessTypeTabs .business-type-tab').removeClass('active');
                    $(this).addClass('active');
                    activeBusinessType = String($(this).attr('data-business-type') || 'all');
                    if (table) { table.draw(); }
                    filterMobileSalesCards();
                });
            }

            function toggleCustomDates() {
                const isCustom = $('#filterPeriod').val() === 'custom';
                $('.js-custom-dates').toggleClass('d-none', !isCustom);
                if (!isCustom) {
                    $('#filterDateFrom, #filterDateTo').val('');
                }
            }

            $('#filterPeriod').on('change', toggleCustomDates);

            const autoPaySaleId = @json(request()->query('pay'));
            const alsoPayRaw = @json(request()->query('also_pay'));
            if (autoPaySaleId) {
                const $payBtn = $('.open-payment-modal-btn[data-sale-id="' + autoPaySaleId + '"]').first();
                if ($payBtn.length) {
                    const alsoPayIds = Array.isArray(alsoPayRaw)
                        ? alsoPayRaw
                        : (alsoPayRaw ? [alsoPayRaw] : []);
                    if (alsoPayIds.length) {
                        $payBtn.attr('data-also-pay', JSON.stringify(alsoPayIds.map(String)));
                    }
                    setTimeout(function () { $payBtn.trigger('click'); }, 400);
                }
            }
        });
    </script>
    @include('sales.partials.customer-picker-scripts')
    @include('sales.partials.payment-modal-scripts')
@endsection
