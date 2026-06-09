@extends('layouts.app')

@section('title', __('pages.debts.title') . ' - SpareParts POS')

@section('styles')
<style>
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff !important; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000 !important; }
  .business-type-tab i { margin-right: 5px; }
  .debts-page .widget-small { min-height: 88px; border-radius: 8px !important; margin-bottom: 12px; }
  .debts-page .widget-small .icon { min-width: 64px !important; padding: 10px !important; font-size: 2rem !important; }
  .debts-page .widget-small .info h4 { font-size: 0.82rem !important; }
  .debts-page .widget-small .info p { font-size: 15px !important; word-break: break-word; }
  .debts-page .debts-filter-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
  .debts-page .debts-filter-form .form-control { min-width: 140px; flex: 1 1 140px; }
  .debts-page .debts-summary-card,
  .debts-page .debts-mobile-card {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
  }
  .debts-page .debts-mobile-card.is-overdue { border-color: #ffc107; background: #fffdf5; }
  .debts-page .debts-summary-head,
  .debts-page .debts-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
  .debts-page .debts-mobile-ref { font-weight: 700; color: #940000; font-size: 0.9rem; }
  .debts-page .debts-mobile-customer { font-weight: 600; margin-top: 2px; }
  .debts-page .debts-mobile-meta,
  .debts-page .debts-summary-meta { font-size: 0.82rem; color: #6c757d; margin-top: 2px; }
  .debts-page .debts-summary-meta { display: flex; flex-wrap: wrap; gap: 12px; }
  .debts-page .debts-mobile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 12px; margin-bottom: 10px; }
  .debts-page .debts-mobile-stat span { display: block; font-size: 0.72rem; text-transform: uppercase; color: #6c757d; font-weight: 600; letter-spacing: 0.02em; }
  .debts-page .debts-mobile-stat strong { display: block; font-size: 0.88rem; margin-top: 2px; word-break: break-word; }
  .debts-page .debts-mobile-actions { display: flex; gap: 8px; padding-top: 8px; border-top: 1px solid #eee; }
  .debts-page .debts-title-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }

  @media (max-width: 991.98px) {
    .debts-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .debts-page .app-title p { font-size: 0.88rem; }
    .debts-page .business-type-tabs { padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
    .debts-page .tile-title-w-btn { flex-direction: column; align-items: stretch !important; }
    .debts-page .tile-title-w-btn .title { margin-bottom: 12px !important; }
    .debts-page .debts-filter-form { width: 100%; }
    .debts-page .debts-filter-form .form-control { flex: 1 1 100%; min-width: 0; }
    .debts-page .debts-filter-form .btn { flex: 1 1 auto; }
  }

  @media (max-width: 767.98px) {
    .debts-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .debts-page .app-title h1 { font-size: 1.15rem; }
    .debts-page .app-title p { font-size: 0.82rem; }
    .debts-page .debts-title-actions { width: 100%; }
    .debts-page .debts-title-actions .btn { flex: 1 1 calc(50% - 4px); text-align: center; }
    .debts-page .widget-small .icon { min-width: 52px !important; font-size: 1.5rem !important; }
  }
</style>
@endsection

@section('content')
<div class="debts-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-credit-card"></i> {{ __('pages.debts.title') }}</h1>
    <p>{{ ($scopedToSelf ?? false) ? __('pages.debts.subtitle_self') : __('pages.debts.subtitle') }}</p>
    <div class="debts-title-actions d-print-none">
      <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-shopping-cart"></i> {{ __('pages.sales.title') }}</a>
      <a href="{{ route('debts.history') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-history"></i> {{ __('pages.debts.debt_history') }}</a>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col-6 col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.debts.total_outstanding') }}</h4>
        <p><b>{{ money($stats['total_outstanding']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-file-text-o fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.debts.open_accounts') }}</h4>
        <p><b>{{ $stats['open_accounts'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.debts.overdue') }}</h4>
        <p><b>{{ $stats['overdue_count'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-users fa-3x"></i>
      <div class="info">
        <h4>{{ __('pages.debts.customers_owing') }}</h4>
        <p><b>{{ $stats['customers'] }}</b></p>
      </div>
    </div>
  </div>
</div>

@if($customerSummaries->isNotEmpty())
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">{{ __('pages.debts.top_balances') }}</h3>
      <div class="tile-body">
        <div class="d-none d-md-block">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.customer') }}</th>
                <th>{{ __('tables.columns.phone') }}</th>
                <th>{{ __('tables.columns.open_orders') }}</th>
                <th>{{ __('tables.columns.total_balance') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($customerSummaries as $customer)
                <tr>
                  <td><strong>{{ $customer['name'] }}</strong></td>
                  <td>{{ $customer['phone'] ?: '—' }}</td>
                  <td>{{ $customer['orders'] }}</td>
                  <td class="text-danger font-weight-bold">{{ money($customer['balance']) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="d-md-none">
          @include('debts.partials.customer-summary-mobile-list', ['customerSummaries' => $customerSummaries])
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      @if($multiBusiness ?? false)
      @php
        $tabQuery = request()->only(['search', 'status', 'filter']);
      @endphp
      <div class="business-type-tabs mb-3">
        <a href="{{ route('debts.index', $tabQuery) }}"
           class="business-type-tab {{ ($activeBusinessType ?? 'all') === 'all' ? 'active' : '' }}">
          <i class="fa fa-th-large"></i> All
        </a>
        @foreach($businessTypes as $type)
        <a href="{{ route('debts.index', array_merge($tabQuery, ['business_type' => $type['key']])) }}"
           class="business-type-tab {{ ($activeBusinessType ?? 'all') === $type['key'] ? 'active' : '' }}">
          <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
        </a>
        @endforeach
      </div>
      @endif
      <div class="tile-title-w-btn">
        <h3 class="title">{{ __('pages.debts.outstanding_debts') }}</h3>
        <form method="GET" action="{{ route('debts.index') }}" class="debts-filter-form">
          @if(($activeBusinessType ?? 'all') !== 'all')
            <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
          @endif
          <input type="text" name="search" class="form-control form-control-sm" placeholder="Search customer, phone, ref..." value="{{ request('search') }}">
          <select name="status" class="form-control form-control-sm">
            <option value="">All Types</option>
            <option value="debt" {{ request('status') === 'debt' ? 'selected' : '' }}>Full Debt</option>
            <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial Payment</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
          </select>
          <select name="filter" class="form-control form-control-sm">
            <option value="">All Dates</option>
            <option value="overdue" {{ request('filter') === 'overdue' ? 'selected' : '' }}>Overdue Only</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-filter"></i> Filter</button>
          @if(request()->hasAny(['search', 'status', 'filter']))
            <a href="{{ route('debts.index') }}" class="btn btn-sm btn-secondary">Clear</a>
          @endif
        </form>
      </div>
      <div class="tile-body">
        <div class="d-lg-none mb-3">
          @include('debts.partials.debt-mobile-list', ['debts' => $debts, 'today' => $today])
        </div>
        <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.reference') }}</th>
              <th>{{ __('tables.columns.customer') }}</th>
              <th>{{ __('tables.columns.phone') }}</th>
              <th>{{ __('tables.columns.sale_total') }}</th>
              <th>{{ __('tables.columns.paid') }}</th>
              <th>{{ __('tables.columns.balance_due') }}</th>
              <th>{{ __('tables.columns.due_date') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>{{ __('tables.columns.cashier') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($debts as $sale)
              @php
                $balance = max(0, $sale->total_amount - $sale->amount_paid);
                $isOverdue = $sale->due_date && $sale->due_date < $today;
              @endphp
              <tr class="{{ $isOverdue ? 'table-warning' : '' }}">
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
                <td>{{ $sale->reference_no }}</td>
                <td><strong>{{ $sale->customer_name ?: 'Walk-in / Unnamed' }}</strong></td>
                <td>{{ $sale->customer_phone ?: '—' }}</td>
                <td>{{ money($sale->total_amount) }}</td>
                <td>{{ money($sale->amount_paid) }}</td>
                <td class="text-danger font-weight-bold">{{ money($balance) }}</td>
                <td>
                  @if($sale->due_date)
                    {{ \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') }}
                    @if($isOverdue)
                      <span class="badge badge-danger">{{ __('tables.status.overdue') }}</span>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($sale->payment_status === 'debt')
                    <span class="badge badge-danger">{{ __('tables.status.debt') }}</span>
                  @elseif($sale->payment_status === 'partial')
                    <span class="badge badge-info">{{ __('tables.status.partial') }}</span>
                  @else
                    <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
                  @endif
                </td>
                <td>{{ $sale->user->name }}</td>
                <td>
                  @php
                    $payItems = $sale->items->map(function ($si) {
                        return [
                            'id' => $si->id,
                            'name' => $si->item->name ?? 'Item',
                            'qty' => (float) $si->quantity,
                            'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
                        ];
                    })->values();
                  @endphp
                  <button type="button"
                    class="btn btn-sm btn-success open-payment-modal-btn"
                    data-sale-id="{{ $sale->id }}"
                    data-ref="{{ e($sale->reference_no) }}"
                    data-total="{{ $sale->total_amount }}"
                    data-paid="{{ $sale->amount_paid }}"
                    data-customer-name="{{ e($sale->customer_name ?? '') }}"
                    data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
                    data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
                    data-items='@json($payItems)'>
                    <i class="fa fa-money"></i> {{ __('tables.actions.collect') }}
                  </button>
                  <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Receipt"><i class="fa fa-eye"></i></a>
                </td>
              </tr>
            @endforeach
            @if($debts->isEmpty())
              <tr>
                <td colspan="11" class="text-center">{{ __('tables.empty.outstanding_debts') }}</td>
              </tr>
            @endif
          </tbody>
        </table>
        </div>
        {{ $debts->links() }}
      </div>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
</div>
@endsection

@section('scripts')
    @include('sales.partials.payment-modal-scripts')
@endsection
