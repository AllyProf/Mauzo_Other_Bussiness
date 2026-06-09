@extends('layouts.app')

@section('title', 'Daily Reconciliation - SpareParts POS')

@section('styles')
<style>
  .day-closing-page #staff-table { border-collapse: collapse !important; border-radius: 8px; overflow: hidden; border: 1px solid #dee2e6 !important; }
  .day-closing-page #staff-table th, .day-closing-page #staff-table td,
  .day-closing-page #boss-staff-table th, .day-closing-page #boss-staff-table td { vertical-align: middle; padding: 12px 10px; border: 1px solid #dee2e6 !important; }
  .day-closing-page #staff-table thead th, .day-closing-page #boss-staff-table thead th { background-color: #2d3436 !important; color: white !important; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
  .day-closing-page .table-responsive { border-radius: 8px; border: 1px solid #dee2e6; }
  .day-closing-page .audit-col-bg { background-color: #f1f7fe !important; }
  .day-closing-page .diff-col-bg { background-color: #fff9f1 !important; }
  .day-closing-page .status-pill { border-radius: 50px; padding: 4px 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
  .day-closing-page .widget-small { min-height: 90px; border-radius: 8px !important; margin-bottom: 15px; }
  .day-closing-page .widget-small.coloured-icon .info,
  .day-closing-page .widget-small.coloured-icon .info h4,
  .day-closing-page .widget-small.coloured-icon .info p,
  .day-closing-page .widget-small.coloured-icon .info b { color: #000 !important; }
  .day-closing-page .widget-small .icon { min-width: 70px !important; padding: 10px !important; font-size: 2rem !important; }
  .day-closing-page .widget-small .info h4 { font-size: 0.8rem !important; margin-bottom: 2px !important; }
  .day-closing-page .widget-small .info p { font-size: 15px !important; word-break: break-word; }
  .day-closing-page .badge { font-weight: 600; padding: 5px 8px; }
  .day-closing-page .dc-filter-form .form-group { margin-bottom: 0.75rem; }
  .day-closing-page .dc-mobile-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
  }
  .day-closing-page .dc-mobile-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
  }
  .day-closing-page .dc-mobile-title { font-weight: 700; line-height: 1.35; }
  .day-closing-page .dc-mobile-meta { font-size: 0.82rem; color: #6c757d; }
  .day-closing-page .dc-mobile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 12px;
  }
  .day-closing-page .dc-mobile-stat span {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
  .day-closing-page .dc-mobile-stat strong {
    display: block;
    font-size: 0.92rem;
    margin-top: 2px;
    word-break: break-word;
  }
  .day-closing-page .final-handover-banner .display-4 { font-size: 2.2rem; }
  .day-closing-page .handover-card .col-md-6 + .col-md-6 { margin-top: 0; }

  @media (max-width: 991.98px) {
    .day-closing-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .day-closing-page .app-title p { font-size: 0.88rem; }
    .day-closing-page .tile { padding: 14px; }
    .day-closing-page .widget-small .icon { min-width: 58px !important; font-size: 1.6rem !important; }
    .day-closing-page .widget-small .info p { font-size: 14px !important; }
  }

  @media (max-width: 767.98px) {
    .day-closing-page .app-title { margin-bottom: 16px; }
    .day-closing-page .app-title h1 { font-size: 1.15rem; }
    .day-closing-page .app-title p { font-size: 0.82rem; }
    .day-closing-page .app-breadcrumb { font-size: 0.85rem; }
    .day-closing-page .dc-filter-form {
      display: block !important;
    }
    .day-closing-page .dc-filter-form .form-group {
      display: block;
      width: 100%;
      margin-right: 0 !important;
    }
    .day-closing-page .dc-filter-form .form-control,
    .day-closing-page .dc-filter-form .btn {
      width: 100%;
    }
    .day-closing-page .dc-filter-form .btn { margin-left: 0 !important; margin-top: 0.25rem; }
    .day-closing-page .tile-title .btn { width: 100%; }
    .day-closing-page .final-handover-banner .display-4 { font-size: 1.75rem; }
    .day-closing-page .final-handover-banner .p-4 { padding: 1.25rem !important; }
    .day-closing-page .handover-summary-cols > [class*="col-"] {
      border-left: none !important;
      margin-bottom: 0.75rem;
    }
    .day-closing-page #submitHandoverBtn,
    .day-closing-page #postOwnerSalesBtn,
    .day-closing-page .verify-handover-form .btn-lg {
      width: 100%;
    }
    .day-closing-page .form-control,
    .day-closing-page select.form-control,
    .day-closing-page textarea.form-control,
    .day-closing-page input[type="date"],
    .day-closing-page input[type="number"],
    .day-closing-page input[type="datetime-local"] {
      font-size: 16px;
    }
    .day-closing-page .modal-dialog {
      margin: 0.5rem;
      max-width: calc(100% - 1rem);
    }
    .day-closing-page .boss-finance-cols > [class*="col-"] {
      border-left: none !important;
    }
  }

  @media (max-width: 575.98px) {
    .day-closing-page .widget-small {
      min-height: auto;
      display: flex;
      align-items: stretch;
    }
    .day-closing-page .widget-small .info p { font-size: 13px !important; }
    .day-closing-page .dc-mobile-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')
<div class="day-closing-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-balance-scale"></i> {{ ($serviceMenuContext ?? false) ? 'Service Handover' : 'Daily Reconciliation' }}</h1>
    <p>
      @if($serviceMenuContext ?? false)
        @if($isBossReview ?? false)
          Review and verify service shift handovers for the selected date
        @elseif($shift ?? null)
          Submit your service shift handover — your shift closes when handover is submitted
        @else
          Review service staff collections and verify handovers
        @endif
      @elseif($isBossReview ?? false)
        Review staff sales and verify shift handovers for the selected date
      @elseif($shift ?? null)
        Review your shift collections and submit handover — your shift closes when handover is submitted
      @else
        Review staff sales, verify collections, and submit handover to your boss
      @endif
    </p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    @if($serviceMenuContext ?? false)
    <li class="breadcrumb-item"><a href="{{ route('services.categories') }}">Services</a></li>
    <li class="breadcrumb-item">Handover</li>
    @else
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reconciliation</li>
    @endif
  </ul>
</div>

@if(($isBossReview ?? false) && ($pendingFromOtherDays ?? collect())->isNotEmpty())
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile border-warning">
      <h3 class="tile-title text-warning mb-0">
        <i class="fa fa-exclamation-triangle"></i>
        Previous Day{{ ($pendingFromOtherDays->count() > 1) ? 's' : '' }} Awaiting Verification ({{ $pendingFromOtherDays->count() }})
      </h3>
      <div class="tile-body pt-3">
        <p class="text-muted mb-3">
          You are viewing <strong>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</strong>, but earlier handovers still need approval before they post to the Master Sheet.
        </p>
        <div class="d-lg-none mb-3">
          @foreach($pendingFromOtherDays as $pending)
          <div class="dc-mobile-card">
            <div class="dc-mobile-head">
              <div>
                <div class="dc-mobile-title">{{ $pending->closing_date->format('M d, Y') }}</div>
                <div class="dc-mobile-meta">{{ $pending->user->name ?? 'Unknown' }} · Shift #{{ $pending->shift?->id ?? '—' }}</div>
              </div>
              <strong>{{ money($pending->net_amount) }}</strong>
            </div>
            <div class="dc-mobile-meta mb-2">Submitted {{ $pending->submitted_at?->format('M d, h:i A') ?? '—' }}</div>
            <a href="{{ route('day-closing.index', ['date' => $pending->closing_date->format('Y-m-d')]) }}#handover-{{ $pending->id }}" class="btn btn-sm btn-warning btn-block">
              <i class="fa fa-check"></i> Review &amp; Verify
            </a>
          </div>
          @endforeach
        </div>
        <div class="table-responsive d-none d-lg-block">
          <table class="table table-bordered table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                <th>{{ __('tables.columns.officer') }}</th>
                <th>{{ __('tables.columns.shift') }}</th>
                <th>Net Handover</th>
                <th>Submitted</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pendingFromOtherDays as $pending)
              <tr>
                <td><strong>{{ $pending->closing_date->format('M d, Y') }}</strong></td>
                <td>{{ $pending->user->name ?? 'Unknown' }}</td>
                <td>{{ $pending->shift ? '#'.$pending->shift->id : '—' }}</td>
                <td><strong>{{ money($pending->net_amount) }}</strong></td>
                <td>{{ $pending->submitted_at?->format('M d, h:i A') ?? '—' }}</td>
                <td class="text-center">
                  <a href="{{ route('day-closing.index', ['date' => $pending->closing_date->format('Y-m-d')]) }}#handover-{{ $pending->id }}" class="btn btn-sm btn-warning">
                    <i class="fa fa-check"></i> Review &amp; Verify
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@elseif(($isBossReview ?? false) && ($pendingVerificationHandovers ?? collect())->isNotEmpty())
<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert alert-warning mb-0">
      <i class="fa fa-hourglass-half"></i>
      <strong>{{ $pendingVerificationHandovers->count() }} handover(s) on this date</strong> still need verification — review below.
    </div>
  </div>
</div>
@endif

@if($shift ?? null)
<div class="alert alert-info mb-0">
  <i class="fa fa-clock-o"></i>
  <strong>Shift #{{ $shift->id }}</strong>
  @if($shift->isOpen())
    open since {{ $shift->opened_at->format('M d, Y h:i A') }}
  @else
    closed at {{ $shift->closed_at->format('M d, Y h:i A') }}
  @endif
  — {{ $summary['sales_count'] ?? $shift->sales_count }} sale(s), {{ money($summary['gross_sales'] ?? $shift->gross_sales) }} gross.
  Submit handover below to close your shift and send collections to your boss.
</div>
@endif

@if(session('info') && ($isBossReview ?? false))
<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert alert-info mb-0">
      <i class="fa fa-info-circle"></i> {{ session('info') }}
      <a href="{{ route('money-shorts.index') }}" class="alert-link font-weight-bold ml-1">Open Money Shorts</a>
    </div>
  </div>
</div>
@endif

<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <form method="GET" action="{{ route('day-closing.index') }}" class="form-inline dc-filter-form">
        @if($shift ?? null)
          <input type="hidden" name="shift" value="{{ $shift->id }}">
          <div class="form-group mr-3">
            <label class="mr-2">Shift Date:</label>
            <span class="font-weight-bold">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</span>
          </div>
        @else
        <div class="form-group mr-3">
          <label for="date" class="mr-2">Select Date:</label>
          <input type="date" name="date" id="date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}" required onchange="this.form.submit()">
        </div>
        @endif
        <div class="form-group mr-3">
          <label for="status-filter" class="mr-2">Status:</label>
          <select id="status-filter" class="form-control">
            <option value="">All Statuses</option>
            <option value="paid">Paid</option>
            <option value="partial">Partial</option>
            <option value="pending">Pending</option>
          </select>
        </div>
        @can('view_reports')
          <a href="{{ route('day-closing.history') }}" class="btn btn-secondary ml-2"><i class="fa fa-history"></i> History</a>
        @endcan
      </form>
    </div>
  </div>
</div>

@unless($isBossReview ?? false)
@php
  $handoverCash = $platformBreakdown['cash']['amount'] ?? $summary['cash_received'];
  $handoverMobile = collect($platformBreakdown)->filter(fn ($p) => ($p['method'] ?? '') === 'mobile_money')->sum('amount');
  $handoverBank = collect($platformBreakdown)->filter(fn ($p) => ($p['method'] ?? '') === 'bank')->sum('amount');
@endphp
<div class="row mb-3">
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-users fa-3x"></i>
      <div class="info"><h4>Active Staff</h4><p><b>{{ count($staffRows) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info"><h4>Gross Sales</h4><p><b>TZS {{ number_format($summary['gross_sales'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info"><h4>Total Cash</h4><p><b>TZS {{ number_format($handoverCash, 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-mobile fa-3x"></i>
      <div class="info"><h4>Digital + Bank</h4><p><b>TZS {{ number_format($handoverMobile + $handoverBank, 0) }}</b></p></div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title d-flex justify-content-between align-items-center flex-wrap">
        <span>Staff Reconciliation — {{ $displayDate }}</span>
        @if(count($staffRows) > 0 || count($allDaySales) > 0)
          <button type="button" class="btn btn-info btn-sm mt-2 mt-md-0" id="viewAllSalesBtn">
            <i class="fa fa-eye"></i> View All Sales ({{ count($allDaySales) }})
          </button>
        @endif
      </h3>
      <div class="tile-body">
        @if(count($staffRows) > 0)
        <div class="d-lg-none mb-3" id="staff-mobile-list">
          @include('day-closing.partials.staff-mobile-cards', ['staffRows' => $staffRows])
        </div>
          <div class="table-responsive shadow-sm mb-3 d-none d-lg-block">
            <table class="table table-hover table-bordered table-striped" id="staff-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ __('tables.columns.staff') }}</th>
                  <th>Orders</th>
                  <th>Gross Sales</th>
                  <th>Cash</th>
                  <th>Mobile</th>
                  <th>Bank</th>
                  <th class="audit-col-bg">Debt Paid</th>
                  <th class="audit-col-bg text-center">Expected</th>
                  <th class="audit-col-bg">Collected</th>
                  <th class="audit-col-bg">Credit</th>
                  <th class="diff-col-bg text-center">Diff</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($staffRows as $index => $data)
                <tr data-status="{{ $data['status'] }}" data-staff-name="{{ strtolower($data['staff']->name ?? '') }}">
                  <td>{{ $index + 1 }}</td>
                  <td>
                    <strong>{{ $data['staff']->name ?? 'Unknown' }}</strong><br>
                    <small class="text-muted">{{ $data['staff']->email ?? '' }}</small>
                  </td>
                  <td><span class="badge badge-info">{{ $data['total_orders'] }}</span></td>
                  <td><strong>TZS {{ number_format($data['gross_sales'], 0) }}</strong></td>
                  <td>TZS {{ number_format($data['cash_collected'], 0) }}</td>
                  <td>TZS {{ number_format($data['mobile_collected'], 0) }}</td>
                  <td>TZS {{ number_format($data['bank_collected'], 0) }}</td>
                  <td class="audit-col-bg">
                    @if(($data['debt_collected'] ?? 0) > 0)
                      <strong class="text-primary">TZS {{ number_format($data['debt_collected'], 0) }}</strong>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="audit-col-bg"><strong>TZS {{ number_format($data['expected_amount'], 0) }}</strong></td>
                  <td class="audit-col-bg"><strong class="text-info">TZS {{ number_format($data['collected_on_orders'], 0) }}</strong></td>
                  <td class="audit-col-bg">
                    @if($data['credit'] > 0)
                      <span class="text-danger">TZS {{ number_format($data['credit'], 0) }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="diff-col-bg text-center">
                    @php $diff = $data['difference']; @endphp
                    @if(abs($diff) < 0.01)
                      <span class="text-muted">—</span>
                    @else
                      <strong class="{{ $diff >= 0 ? 'text-success' : 'text-danger' }}">
                        @if($diff > 0)+@endif{{ number_format($diff, 0) }}
                      </strong>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($data['status'] === 'paid')
                      <span class="status-pill badge-success">Paid</span>
                    @elseif($data['status'] === 'partial')
                      <span class="status-pill badge-warning">Partial</span>
                    @else
                      <span class="status-pill badge-warning">Pending</span>
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="alert alert-info"><i class="fa fa-info-circle"></i> No sales found for this date.</div>
        @endif

        @if(($debtCollections['count'] ?? 0) > 0)
          <h4 class="mt-4 mb-3"><i class="fa fa-history"></i> Debt Collections Today ({{ $debtCollections['count'] }})</h4>
          <p class="text-muted small mb-2">
            @if($shift ?? null)
              Credit payments you collected today on sales outside this shift — included in your handover total below.
            @else
              Payments collected today on credit sales outside today's shift sales — included in handover but separate from shift sales.
            @endif
          </p>
          <div class="d-lg-none mb-3">
            @foreach($debtCollections['items'] as $item)
            <div class="dc-mobile-card">
              <div class="dc-mobile-head">
                <div>
                  <div class="dc-mobile-title">{{ $item['customer'] }}</div>
                  <div class="dc-mobile-meta">{{ $item['collected_at'] }} · {{ $item['collected_by'] }}</div>
                </div>
                <strong class="text-success">{{ money($item['amount']) }}</strong>
              </div>
              <div class="dc-mobile-meta">
                Ref: {{ $item['sale_ref'] }} · Sale {{ $item['sale_date'] }}<br>
                {{ ucfirst(str_replace('_', ' ', $item['method'])) }}
                @if($item['provider'] || $item['reference'])
                  · {{ $item['provider'] ?: '—' }}
                  @if($item['reference']) ({{ $item['reference'] }}) @endif
                @endif
              </div>
            </div>
            @endforeach
            <div class="alert alert-primary py-2 mb-0">
              <strong>Total Debt Collected:</strong> {{ money($debtCollections['total']) }}
            </div>
          </div>
          <div class="table-responsive shadow-sm d-none d-lg-block">
            <table class="table table-hover table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th>{{ __('tables.columns.time') }}</th>
                  <th>Collected By</th>
                  <th>{{ __('tables.columns.customer') }}</th>
                  <th>Sale Ref</th>
                  <th>Sale Date</th>
                  <th>{{ __('tables.columns.amount') }}</th>
                  <th>{{ __('tables.columns.method') }}</th>
                  <th>Provider / Ref</th>
                </tr>
              </thead>
              <tbody>
                @foreach($debtCollections['items'] as $item)
                  <tr>
                    <td>{{ $item['collected_at'] }}</td>
                    <td>{{ $item['collected_by'] }}</td>
                    <td><strong>{{ $item['customer'] }}</strong></td>
                    <td>{{ $item['sale_ref'] }}</td>
                    <td>{{ $item['sale_date'] }}</td>
                    <td class="text-success font-weight-bold">{{ money($item['amount']) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $item['method'])) }}</td>
                    <td>
                      @if($item['provider'] || $item['reference'])
                        {{ $item['provider'] ?: '—' }}
                        @if($item['reference'])
                          <small class="text-muted">({{ $item['reference'] }})</small>
                        @endif
                      @else
                        —
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="table-primary">
                  <th colspan="5" class="text-right">Total Debt Collected</th>
                  <th colspan="3">{{ money($debtCollections['total']) }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endunless

@if($isBossReview ?? false)
<div class="row mb-3">
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-users fa-3x"></i>
      <div class="info"><h4>Active Staff</h4><p><b>{{ count($staffRows) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info"><h4>Gross Sales</h4><p><b>TZS {{ number_format($summary['gross_sales'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-3 mb-md-0">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info"><h4>Total Cash</h4><p><b>TZS {{ number_format($summary['cash_received'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-mobile fa-3x"></i>
      <div class="info"><h4>Digital + Bank</h4><p><b>TZS {{ number_format($summary['mobile_received'] + $summary['bank_received'], 0) }}</b></p></div>
    </div>
  </div>
</div>

@if(($summary['sales_count'] ?? 0) > 0)
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title d-flex justify-content-between align-items-center flex-wrap">
        <span><i class="fa fa-shopping-cart"></i> POS Sales — {{ $displayDate }}</span>
        @if(count($allDaySales) > 0)
          <button type="button" class="btn btn-info btn-sm mt-2 mt-md-0" id="viewAllSalesBtnBoss">
            <i class="fa fa-eye"></i> View All Sales ({{ count($allDaySales) }})
          </button>
        @endif
      </h3>
      <div class="tile-body">
        @if(count($businessTypeBreakdown ?? []) > 0)
          <h5 class="mb-3"><i class="fa fa-sitemap"></i> By Business Type — Profit, Circulation &amp; Debt</h5>
          <p class="text-muted small mb-3">
            Profit and circulation split follows your shop setting
            (<strong>{{ ($expenseDeductFrom ?? 'circulation') === 'profit' ? 'expenses from profit' : 'expenses from circulation' }}</strong>).
            @if(($expenseDeductFrom ?? 'circulation') === 'circulation')
              Circulation = cash collected minus profit; profit is capped at what was collected when payment is partial.
            @else
              Full sale profit is counted; all collections add to circulation.
            @endif
          </p>
          <div class="d-lg-none mb-3">
            @foreach($businessTypeBreakdown as $typeRow)
            <div class="dc-mobile-card">
              <div class="dc-mobile-title mb-2">{{ $typeRow['label'] }}</div>
              <div class="dc-mobile-grid">
                <div class="dc-mobile-stat"><span>Orders</span><strong>{{ $typeRow['orders'] }}</strong></div>
                <div class="dc-mobile-stat"><span>Gross</span><strong>{{ money($typeRow['gross_sales']) }}</strong></div>
                <div class="dc-mobile-stat"><span>Collected</span><strong>{{ money($typeRow['collected']) }}</strong></div>
                <div class="dc-mobile-stat"><span>New debt</span><strong class="text-danger">{{ $typeRow['credit'] > 0 ? money($typeRow['credit']) : '—' }}</strong></div>
                <div class="dc-mobile-stat"><span>Debt paid</span><strong class="text-primary">{{ ($typeRow['debt_collected'] ?? 0) > 0 ? money($typeRow['debt_collected']) : '—' }}</strong></div>
                <div class="dc-mobile-stat"><span>Profit</span><strong class="text-success">{{ money($typeRow['profit_generated']) }}</strong></div>
                <div class="dc-mobile-stat"><span>Circulation</span><strong class="text-warning">{{ money($typeRow['circulation_generated']) }}</strong></div>
              </div>
            </div>
            @endforeach
          </div>
          <div class="table-responsive shadow-sm mb-4 d-none d-lg-block">
            <table class="table table-hover table-bordered table-sm">
              <thead class="thead-light">
                <tr>
                  <th>{{ __('tables.columns.business') }}</th>
                  <th>Orders</th>
                  <th>Gross Sales</th>
                  <th>{{ __('tables.columns.collected') }}</th>
                  <th class="text-danger">New Debt</th>
                  <th class="text-primary">Debt Paid</th>
                  <th class="text-success">Profit</th>
                  <th class="text-warning">Circulation</th>
                </tr>
              </thead>
              <tbody>
                @foreach($businessTypeBreakdown as $typeRow)
                <tr>
                  <td><strong>{{ $typeRow['label'] }}</strong></td>
                  <td><span class="badge badge-info">{{ $typeRow['orders'] }}</span></td>
                  <td><strong>TZS {{ number_format($typeRow['gross_sales'], 0) }}</strong></td>
                  <td>TZS {{ number_format($typeRow['collected'], 0) }}</td>
                  <td>
                    @if($typeRow['credit'] > 0)
                      <span class="text-danger font-weight-bold">TZS {{ number_format($typeRow['credit'], 0) }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if(($typeRow['debt_collected'] ?? 0) > 0)
                      <span class="text-primary font-weight-bold">TZS {{ number_format($typeRow['debt_collected'], 0) }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td><span class="text-success font-weight-bold">TZS {{ number_format($typeRow['profit_generated'], 0) }}</span></td>
                  <td><span class="text-warning font-weight-bold">TZS {{ number_format($typeRow['circulation_generated'], 0) }}</span></td>
                </tr>
                @endforeach
              </tbody>
              <tfoot class="thead-light">
                <tr>
                  <th>{{ __('tables.columns.total') }}</th>
                  <th>{{ $businessTypeTotals['orders'] ?? 0 }}</th>
                  <th>TZS {{ number_format($businessTypeTotals['gross_sales'] ?? 0, 0) }}</th>
                  <th>TZS {{ number_format($businessTypeTotals['collected'] ?? 0, 0) }}</th>
                  <th class="text-danger">TZS {{ number_format($businessTypeTotals['credit'] ?? 0, 0) }}</th>
                  <th class="text-primary">TZS {{ number_format($businessTypeTotals['debt_collected'] ?? 0, 0) }}</th>
                  <th class="text-success">TZS {{ number_format($businessTypeTotals['profit_generated'] ?? 0, 0) }}</th>
                  <th class="text-warning">TZS {{ number_format($businessTypeTotals['circulation_generated'] ?? 0, 0) }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        @endif

        @if(count($staffRows) > 0)
          <div class="d-lg-none mb-3">
            @include('day-closing.partials.staff-mobile-cards', ['staffRows' => $staffRows, 'showDiff' => false])
          </div>
          <div class="table-responsive shadow-sm mb-3 d-none d-lg-block">
            <table class="table table-hover table-bordered table-striped" id="boss-staff-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Seller</th>
                  <th>Orders</th>
                  <th>Gross Sales</th>
                  <th>Cash</th>
                  <th>Mobile</th>
                  <th>Bank</th>
                  <th class="audit-col-bg">Debt Paid</th>
                  <th class="audit-col-bg text-center">Expected</th>
                  <th class="audit-col-bg">Collected</th>
                  <th class="audit-col-bg">Credit</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($staffRows as $index => $data)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td>
                    <strong>{{ $data['staff']->name ?? 'Unknown' }}</strong>
                    @if(($data['staff']->role ?? '') === 'owner')
                      <span class="badge badge-secondary ml-1">Owner</span>
                    @endif
                  </td>
                  <td><span class="badge badge-info">{{ $data['total_orders'] }}</span></td>
                  <td><strong>TZS {{ number_format($data['gross_sales'], 0) }}</strong></td>
                  <td>TZS {{ number_format($data['cash_collected'], 0) }}</td>
                  <td>TZS {{ number_format($data['mobile_collected'], 0) }}</td>
                  <td>TZS {{ number_format($data['bank_collected'], 0) }}</td>
                  <td class="audit-col-bg">
                    @if(($data['debt_collected'] ?? 0) > 0)
                      <strong class="text-primary">TZS {{ number_format($data['debt_collected'], 0) }}</strong>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="audit-col-bg"><strong>TZS {{ number_format($data['expected_amount'], 0) }}</strong></td>
                  <td class="audit-col-bg"><strong class="text-info">TZS {{ number_format($data['collected_on_orders'], 0) }}</strong></td>
                  <td class="audit-col-bg">
                    @if($data['credit'] > 0)
                      <span class="text-danger">TZS {{ number_format($data['credit'], 0) }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($data['status'] === 'posted')
                      <span class="status-pill badge-success">Posted</span>
                    @elseif($data['status'] === 'paid')
                      <span class="status-pill badge-success">Paid</span>
                    @elseif($data['status'] === 'partial')
                      <span class="status-pill badge-warning">Partial</span>
                    @else
                      <span class="status-pill badge-warning">Pending</span>
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        @if(!($ownerDirectClosing ?? null))
        <div class="alert alert-light border mb-3">
          <i class="fa fa-info-circle"></i>
          @if($canPostOwnerDirectSales ?? false)
            When you sell yourself, use <strong>Close Day &amp; Post to Master Sheet</strong> below — no separate staff handover is needed.
          @else
            Your direct POS sales are included above and do not require a shift handover. Sales officers submit handover when they end their shift.
          @endif
        </div>
        @endif

        @if($canPostOwnerDirectSales ?? false)
          <div class="alert alert-warning border mb-0">
            <form method="POST" action="{{ route('day-closing.post-owner-sales') }}" id="postOwnerSalesForm">
              @csrf
              <input type="hidden" name="closing_date" value="{{ $date }}">

              <div class="mb-3">
                <strong><i class="fa fa-book"></i> Close your day</strong><br>
                <span class="small">
                  Confirm {{ $ownerDirectSummary['sales_count'] ?? 0 }} sale(s),
                  TZS {{ number_format($ownerDirectSummary['gross_sales'] ?? 0, 0) }} gross /
                  TZS {{ number_format($ownerDirectSummary['amount_collected'] ?? 0, 0) }} collected on orders.
                </span>
              </div>

              <div class="row">
                <div class="col-12 col-md-4 mb-3">
                  <label class="font-weight-bold text-muted small text-uppercase mb-1">Expected Handover</label>
                  <div class="form-control bg-light font-weight-bold">{{ money($ownerDirectExpectedHandover ?? 0) }}</div>
                  <small class="text-muted">Total payments recorded for your sales today</small>
                </div>
                <div class="col-12 col-md-4 mb-3">
                  <label for="ownerActualReceived" class="font-weight-bold mb-1">Actual Amount Received <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
                    <input type="number"
                           id="ownerActualReceived"
                           name="actual_received"
                           class="form-control font-weight-bold"
                           min="0"
                           step="1"
                           required
                           value="{{ old('actual_received', round($ownerDirectExpectedHandover ?? 0)) }}"
                           data-expected="{{ round($ownerDirectExpectedHandover ?? 0) }}">
                  </div>
                  <small class="text-muted">Enter a lower amount if you are short</small>
                </div>
                <div class="col-12 col-md-4 mb-3">
                  <label class="font-weight-bold text-muted small text-uppercase mb-1">Money Short</label>
                  <div class="form-control bg-light font-weight-bold text-danger" id="ownerMoneyShortDisplay">—</div>
                </div>
              </div>

              <div class="form-group mb-3" id="ownerShortageNoteWrap" style="display: none;">
                <label for="ownerShortageNote" class="font-weight-bold">Shortage Explanation <span class="text-danger">*</span></label>
                <textarea id="ownerShortageNote"
                          name="shortage_note"
                          class="form-control"
                          rows="2"
                          placeholder="Explain why the amount received is less than expected...">{{ old('shortage_note') }}</textarea>
              </div>

              <div class="form-group mb-3">
                <label for="ownerReportNotes" class="font-weight-bold">Note (Optional)</label>
                <textarea id="ownerReportNotes"
                          name="report_notes"
                          class="form-control"
                          rows="2"
                          placeholder="Any other notes for this day...">{{ old('report_notes') }}</textarea>
              </div>

              <div class="d-flex flex-wrap justify-content-between align-items-center">
                <p class="small text-muted mb-2 mb-md-0">
                  This posts your sales to the Master Sheet and closes any open shift you used today.
                </p>
                <button type="button" class="btn btn-primary" id="postOwnerSalesBtn">
                  <i class="fa fa-check"></i> Close Day &amp; Post to Master Sheet
                </button>
              </div>
            </form>
          </div>
        @elseif($ownerDirectCloseCard ?? null)
          @include('day-closing.partials.owner-direct-close-summary', ['date' => $date])
        @endif
      </div>
    </div>
  </div>
</div>
@endif
@endif

@php
  $overallTotal = collect($platformBreakdown)->sum('amount');
  $totalCash = $platformBreakdown['cash']['amount'] ?? 0;
  $totalMobile = collect($platformBreakdown)->filter(fn($p) => ($p['method'] ?? '') === 'mobile_money')->sum('amount');
  $totalBank = collect($platformBreakdown)->filter(fn($p) => ($p['method'] ?? '') === 'bank')->sum('amount');
@endphp

@if($isBossReview ?? false)
@if(($awaitingHandoverShifts ?? collect())->isNotEmpty())
<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-clock-o"></i> Awaiting Staff Handover — {{ $displayDate }}</h3>
      <div class="tile-body">
        <div class="d-lg-none mb-3">
          @foreach($awaitingHandoverShifts as $pendingShift)
          <div class="dc-mobile-card">
            <div class="dc-mobile-title">{{ $pendingShift->user->name ?? 'Unknown' }}</div>
            <div class="dc-mobile-meta mt-1">Opened {{ $pendingShift->opened_at->format('M d, Y h:i A') }}</div>
            <div class="mt-2">
              @if($pendingShift->isOpen())
                <span class="badge badge-primary">Shift in progress</span>
              @else
                <span class="badge badge-secondary">Awaiting handover</span>
              @endif
            </div>
          </div>
          @endforeach
        </div>
        <div class="table-responsive d-none d-lg-block">
          <table class="table table-bordered">
            <thead class="thead-light">
              <tr>
                <th>{{ __('tables.columns.officer') }}</th>
                <th>{{ __('tables.columns.opened') }}</th>
                <th>{{ __('tables.columns.status') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($awaitingHandoverShifts as $pendingShift)
              <tr>
                <td><strong>{{ $pendingShift->user->name ?? 'Unknown' }}</strong></td>
                <td>{{ $pendingShift->opened_at->format('M d, Y h:i A') }}</td>
                <td>
                  @if($pendingShift->isOpen())
                    <span class="badge badge-primary">Shift in progress</span>
                  @else
                    <span class="badge badge-secondary">Awaiting handover</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

@if(($handoverCards ?? collect())->isNotEmpty())
  @foreach($handoverCards as $card)
    @include('day-closing.partials.handover-card', $card)
  @endforeach
@elseif(($awaitingHandoverShifts ?? collect())->isEmpty() && ($summary['sales_count'] ?? 0) === 0)
<div class="row">
  <div class="col-md-12">
    <div class="alert alert-info">
      <i class="fa fa-info-circle"></i> No staff handovers for this date yet. Sales officers submit handover when they end their shift.
    </div>
  </div>
</div>
@endif
@else

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-handshake-o"></i> Handover to Boss — {{ $displayDate }}</h3>
      <div class="tile-body">
        <div class="alert alert-info border-primary mb-4 p-3 shadow-sm rounded">
          <h5><i class="fa fa-calculator"></i> Handover Summary</h5>
          <div class="row text-center mt-3 handover-summary-cols">
            <div class="col-6 col-md-3 mb-2 mb-md-0">
              <small class="text-uppercase font-weight-bold text-muted">Total Cash</small>
              <h4 class="text-success mb-0">TZS {{ number_format($totalCash, 0) }}</h4>
            </div>
            <div class="col-6 col-md-3 mb-2 mb-md-0">
              <small class="text-uppercase font-weight-bold text-muted">Mobile Money</small>
              <h4 class="text-success mb-0">TZS {{ number_format($totalMobile, 0) }}</h4>
            </div>
            <div class="col-6 col-md-3 mb-2 mb-md-0" style="border-left: 1px solid #dee2e6;">
              <small class="text-uppercase font-weight-bold text-muted">Bank</small>
              <h4 class="text-success mb-0">TZS {{ number_format($totalBank, 0) }}</h4>
            </div>
            <div class="col-12 col-md-3" style="border-left: 1px solid #dee2e6;">
              <small class="text-uppercase font-weight-bold text-muted">Gross Collections</small>
              <h4 class="text-primary mb-0">TZS {{ number_format($overallTotal, 0) }}</h4>
            </div>
          </div>
        </div>

        <form action="{{ route('day-closing.store') }}" method="POST" id="handoverForm">
          @csrf
          <input type="hidden" name="closing_date" value="{{ $date }}">
          @if($shift ?? null)
            <input type="hidden" name="shift_id" value="{{ $shift->id }}">
          @endif

          @if(!($canSubmitHandover ?? true))
            <div class="alert alert-info mb-3">
              <h5 class="mb-1"><i class="fa fa-info-circle"></i> No active shift</h5>
              <p class="mb-0">Open a shift from <a href="{{ route('shifts.index') }}" class="alert-link font-weight-bold">Sales Shifts</a> before you can submit handover.</p>
            </div>
          @else
          <div class="alert alert-warning">
            <h5><i class="fa fa-warning"></i> {{ ($shift ?? null) ? 'Ready to Submit Shift Handover?' : 'Ready to Close Your Day?' }}</h5>
            <p>
              @if($shift ?? null)
                Confirm collections from <strong>Shift #{{ $shift->id }}</strong> for <strong>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</strong>.
                @if($shift->isOpen())
                  Submitting handover will <strong>close your shift</strong>.
                @endif
              @else
                Confirm collections by platform (M-Pesa, CRDB, cash, etc.) for <strong>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</strong>.
              @endif
            </p>
          </div>
          @endif

          <h6 class="text-muted text-uppercase mb-1">Collection Breakdown by Platform</h6>
          <p class="small text-muted mb-3">
            <i class="fa fa-lock"></i> Includes this shift's sales plus any credit payments you collected today. Amounts cannot be edited — use <strong>Record Expense</strong> to deduct shift expenses.
          </p>
          <div class="row">
            @forelse($platformBreakdown as $key => $platform)
              @if($platform['amount'] != 0)
              <div class="col-12 col-sm-6 col-md-4 form-group">
                <label>{{ $platform['label'] }}</label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
                  <div class="form-control bg-light handover-display text-right font-weight-bold"
                       data-platform-key="{{ $key }}"
                       data-platform-label="{{ $platform['label'] }}">{{ number_format(round($platform['amount'])) }}</div>
                  <input type="hidden"
                         name="platform_amounts[{{ $key }}]"
                         class="handover-hidden"
                         data-platform-key="{{ $key }}"
                         data-platform-label="{{ $platform['label'] }}"
                         value="{{ round($platform['amount']) }}">
                </div>
              </div>
              @endif
            @empty
              <div class="col-md-12">
                <div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No payments recorded for this date yet.</div>
              </div>
            @endforelse
          </div>

          <div class="d-flex justify-content-end mt-3 mb-2">
            @if($canSubmitHandover ?? true)
            <button type="button" class="btn btn-sm btn-outline-primary" id="recordExpenseBtn">
              <i class="fa fa-plus-circle"></i> Record Expense
            </button>
            @endif
          </div>

          <div id="expenseHiddenFields"></div>
          <div id="recordedExpensesWrap" class="mb-3" style="display: none;">
            <ul id="expenseListItems" class="list-group list-group-flush border rounded"></ul>
          </div>

          <div class="final-handover-banner mb-3 rounded overflow-hidden shadow-sm">
            <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #940000 0%, #6d0000 100%);">
              <div class="text-uppercase small font-weight-bold mb-1" style="letter-spacing: 0.08em; opacity: 0.9;">Final Amount to Handover</div>
              <div class="display-4 font-weight-bold mb-0"><span id="handover-total">TZS {{ number_format($overallTotal, 0) }}</span></div>
              <div class="small mt-2" style="opacity: 0.92;">Amount you will submit to your boss after expenses</div>
            </div>
            <div class="bg-light px-4 py-3 border-top text-center small">
              <span class="text-muted">Gross collected:</span> <strong id="handover-gross-amount">TZS {{ number_format($overallTotal, 0) }}</strong>
              <span id="handover-expense-line" class="text-muted" style="display: none;">
                <span class="mx-1">−</span> Expenses: <strong class="text-danger" id="handover-expense-amount">TZS 0</strong>
              </span>
            </div>
          </div>

          <div class="form-group">
            <label>Note to Boss (Optional)</label>
            <textarea name="report_notes" class="form-control" rows="2" placeholder="Any explanations for shortages or extra cash...">{{ old('report_notes') }}</textarea>
          </div>

          @if($canSubmitHandover ?? true)
          <button type="button" id="submitHandoverBtn" class="btn btn-primary btn-block">
            <i class="fa fa-paper-plane"></i>
            @if(($shift ?? null) && $shift->isOpen())
              Submit Handover &amp; Close Shift
            @else
              Submit Handover to Boss
            @endif
          </button>
          @endif
        </form>
      </div>
    </div>
  </div>
</div>
@endif

<div class="modal fade" id="salesModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">All Sales — {{ $displayDate }}</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><div id="sales-content"></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
</div>
@endsection

@section('scripts')
@if(!($isBossReview ?? false))
@include('day-closing.partials.sales-modal-render')
<script>
jQuery(function($) {
  const allDaySales = @json($allDaySales);
  const shiftIsOpen = @json(($shift ?? null) && $shift->isOpen());
  const platformOptions = @json(collect($platformBreakdown)->map(fn($p, $k) => ['key' => $k, 'label' => $p['label']])->values());
  let expenses = [];
  const originalPlatforms = {};

  $('.handover-hidden').each(function() {
    originalPlatforms[$(this).data('platform-key')] = parseFloat($(this).val()) || 0;
  });

  function formatPlatformAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
  }

  function getHandoverGross() {
    let total = 0;
    $('.handover-hidden').each(function() { total += parseFloat($(this).val()) || 0; });
    return total;
  }

  function getExpenseTotal() {
    return expenses.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);
  }

  function refreshPlatformAmounts() {
    Object.entries(originalPlatforms).forEach(([key, val]) => {
      $(`.handover-hidden[data-platform-key="${key}"]`).val(val);
      $(`.handover-display[data-platform-key="${key}"]`).text(formatPlatformAmount(val));
    });

    expenses.forEach(exp => {
      const input = $(`.handover-hidden[data-platform-key="${exp.payment_method}"]`);
      const display = $(`.handover-display[data-platform-key="${exp.payment_method}"]`);
      if (input.length) {
        const current = parseFloat(input.val()) || 0;
        const next = Math.max(0, current - parseFloat(exp.amount));
        input.val(next);
        display.text(formatPlatformAmount(next));
      }
    });

    updateHandoverTotal();
  }

  function syncExpenseHiddenFields() {
    const container = $('#expenseHiddenFields').empty();
    expenses.forEach((exp, i) => {
      container.append($('<input>', { type: 'hidden', name: `expenses[${i}][description]`, value: exp.description }));
      container.append($('<input>', { type: 'hidden', name: `expenses[${i}][amount]`, value: exp.amount }));
      container.append($('<input>', { type: 'hidden', name: `expenses[${i}][payment_method]`, value: exp.payment_method }));
    });
  }

  function renderExpenseList() {
    const wrap = $('#recordedExpensesWrap');
    const list = $('#expenseListItems').empty();

    if (!expenses.length) {
      wrap.hide();
    } else {
      expenses.forEach((exp, i) => {
        const label = platformOptions.find(p => p.key === exp.payment_method)?.label || exp.payment_method;
        list.append(`
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <strong>${exp.description}</strong>
              <span class="badge badge-light ml-2">${label}</span>
            </div>
            <div>
              <span class="text-danger font-weight-bold mr-2">- TZS ${Number(exp.amount).toLocaleString()}</span>
              <button type="button" class="btn btn-xs btn-danger remove-expense" data-index="${i}"><i class="fa fa-trash"></i></button>
            </div>
          </li>`);
      });
      wrap.show();
    }

    syncExpenseHiddenFields();
    refreshPlatformAmounts();
  }

  function buildPlatformSelectOptions() {
    let html = '';
    platformOptions.forEach(p => {
      html += `<option value="${p.key}">${p.label}</option>`;
    });
    if (!html) {
      html = '<option value="cash">Physical Cash</option>';
    }
    return html;
  }

  function updateHandoverTotal() {
    const expenseTotal = getExpenseTotal();
    const gross = Object.values(originalPlatforms).reduce((sum, val) => sum + (parseFloat(val) || 0), 0);
    const net = Math.max(0, gross - expenseTotal);

    $('#handover-gross-amount').text('TZS ' + gross.toLocaleString());
    $('#handover-total').text('TZS ' + net.toLocaleString());

    if (expenseTotal > 0) {
      $('#handover-expense-line').show();
      $('#handover-expense-amount').text('TZS ' + expenseTotal.toLocaleString());
    } else {
      $('#handover-expense-line').hide();
    }
  }

  $('#recordExpenseBtn').on('click', function() {
    Swal.fire({
      title: 'Record Expense',
      html: `
        <div class="text-left">
          <div class="form-group">
            <label class="font-weight-bold">Amount (TZS)</label>
            <input type="number" id="expense-amount" class="form-control" placeholder="e.g. 10000" min="0.01" step="0.01">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Description</label>
            <input type="text" id="expense-desc" class="form-control" placeholder="e.g. Transport, lunch, supplies...">
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold">Paid From</label>
            <select id="expense-method" class="form-control">${buildPlatformSelectOptions()}</select>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Save Expense',
      preConfirm: () => {
        const popup = Swal.getPopup();
        const amount = parseFloat(popup.querySelector('#expense-amount').value);
        const desc = popup.querySelector('#expense-desc').value.trim();
        const method = popup.querySelector('#expense-method').value;
        if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
        if (!desc) { Swal.showValidationMessage('Enter a description'); return false; }
        return { amount, desc, method };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        expenses.push({
          description: result.value.desc,
          amount: result.value.amount,
          payment_method: result.value.method
        });
        renderExpenseList();
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Expense added', showConfirmButton: false, timer: 1500 });
      }
    });
  });

  $(document).on('click', '.remove-expense', function() {
    const index = $(this).data('index');
    const exp = expenses[index];
    if (!exp) return;

    Swal.fire({
      title: 'Delete Expense?',
      text: 'Remove "' + exp.description + '" (TZS ' + Number(exp.amount).toLocaleString() + ')? The platform amount will be restored.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it'
    }).then((result) => {
      if (result.isConfirmed) {
        expenses.splice(index, 1);
        renderExpenseList();
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Expense removed', showConfirmButton: false, timer: 1500 });
      }
    });
  });

  $('#status-filter').on('change', function() {
    const val = $(this).val();
    $('#staff-table tbody tr, #staff-mobile-list .dc-mobile-card').each(function() {
      if (!val || $(this).data('status') === val) $(this).show();
      else $(this).hide();
    });
  });

  $('#viewAllSalesBtn').on('click', function() {
    renderDayClosingSalesModal(allDaySales, 'All Sales — {{ $displayDate }}');
  });

  $('#submitHandoverBtn').on('click', function() {
    const net = getHandoverGross();
    const grossBeforeExpenses = Object.values(originalPlatforms).reduce((s, v) => s + v, 0);
    if (net <= 0 && grossBeforeExpenses <= 0) {
      Swal.fire({ icon: 'error', title: 'Empty Handover', text: 'No collections to submit for this day.' });
      return;
    }
    Swal.fire({
      title: shiftIsOpen ? 'Submit Handover & Close Shift?' : 'Submit Handover?',
      text: shiftIsOpen
        ? 'Submit TZS ' + net.toLocaleString() + ' net collection and close your shift? You will need a new shift to sell again.'
        : 'Submit TZS ' + net.toLocaleString() + ' net collection to your boss?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: shiftIsOpen ? 'Yes, submit & close shift' : 'Yes, submit!'
    }).then(r => { if (r.isConfirmed) $('#handoverForm').submit(); });
  });

  updateHandoverTotal();
});
</script>
@else
@include('day-closing.partials.sales-modal-render')
<script>
jQuery(function($) {
  const allDaySales = @json($allDaySales);

  $('#viewAllSalesBtnBoss').on('click', function() {
    renderDayClosingSalesModal(allDaySales, 'All Sales — {{ $displayDate }}');
  });

  $('#postOwnerSalesBtn').on('click', function() {
    const $input = $('#ownerActualReceived');
    const expected = parseFloat($input.data('expected')) || 0;
    const actual = parseFloat($input.val()) || 0;
    const short = Math.max(0, Math.round(expected - actual));
    const note = ($('#ownerShortageNote').val() || '').trim();

    if (actual < 0 || $input.val() === '') {
      Swal.fire({ icon: 'warning', title: 'Amount required', text: 'Enter the actual amount you received.' });
      return;
    }

    if (short > 0 && !note) {
      Swal.fire({ icon: 'warning', title: 'Shortage note required', text: 'Explain why the amount received is less than expected.' });
      return;
    }

    const confirmHtml = short > 0
      ? 'Expected <strong>TZS ' + expected.toLocaleString() + '</strong>, received <strong>TZS ' + actual.toLocaleString() + '</strong>.<br><br>Record a money short of <strong>TZS ' + short.toLocaleString() + '</strong> and post to the Master Sheet?'
      : 'Confirm handover of <strong>TZS ' + actual.toLocaleString() + '</strong> for <strong>{{ $displayDate }}</strong> and post to the Master Sheet?';

    Swal.fire({
      title: short > 0 ? 'Close Day With Money Short?' : 'Close Day & Post to Master Sheet?',
      html: confirmHtml,
      icon: short > 0 ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: short > 0 ? 'Yes, record short & post' : 'Yes, close day'
    }).then((result) => {
      if (result.isConfirmed) {
        $('#postOwnerSalesForm').submit();
      }
    });
  });

  function updateOwnerMoneyShortDisplay() {
    const $input = $('#ownerActualReceived');
    if (!$input.length) {
      return;
    }

    const expected = parseFloat($input.data('expected')) || 0;
    const actual = parseFloat($input.val()) || 0;
    const short = Math.max(0, Math.round(expected - actual));
    const $display = $('#ownerMoneyShortDisplay');
    const $noteWrap = $('#ownerShortageNoteWrap');
    const $note = $('#ownerShortageNote');

    if (short > 0) {
      $display.text('TZS ' + short.toLocaleString());
      $noteWrap.show();
      $note.prop('required', true);
    } else {
      $display.text('—').removeClass('text-danger');
      $noteWrap.hide();
      $note.prop('required', false);
    }
  }

  $('#ownerActualReceived').on('input', updateOwnerMoneyShortDisplay);
  updateOwnerMoneyShortDisplay();

  function updateMoneyShortDisplay($input) {
    const closingId = $input.closest('.verify-handover-form').data('closing-id');
    const expected = parseFloat($input.data('expected')) || 0;
    const actual = parseFloat($input.val()) || 0;
    const short = Math.max(0, Math.round(expected - actual));
    const $display = $('#money-short-display-' + closingId);
    const $noteWrap = $('#shortage-note-wrap-' + closingId);
    const $note = $('#shortage-note-' + closingId);

    if (short > 0) {
      $display.text('TZS ' + short.toLocaleString()).addClass('text-danger');
      $noteWrap.show();
      $note.prop('required', true);
    } else {
      $display.text('—').removeClass('text-danger');
      $noteWrap.hide();
      $note.prop('required', false);
    }
  }

  $('.actual-received-input').each(function() {
    updateMoneyShortDisplay($(this));
  }).on('input', function() {
    updateMoneyShortDisplay($(this));
  });

  $('.verify-handover-form').on('submit', function(e) {
    const $form = $(this);
    const closingId = $form.data('closing-id');
    const $input = $('#actual-received-' + closingId);
    const expected = parseFloat($input.data('expected')) || 0;
    const actual = parseFloat($input.val()) || 0;
    const short = Math.max(0, Math.round(expected - actual));
    const note = ($('#shortage-note-' + closingId).val() || '').trim();

    if (short > 0 && !note) {
      e.preventDefault();
      Swal.fire({ icon: 'warning', title: 'Shortage note required', text: 'Explain why the staff handed over less than expected.' });
      return false;
    }

    e.preventDefault();
    const confirmText = short > 0
      ? 'Expected TZS ' + expected.toLocaleString() + ', received TZS ' + actual.toLocaleString() + '. Record a money short of TZS ' + short.toLocaleString() + ' and post to the Master Sheet?'
      : 'Confirm full handover of TZS ' + actual.toLocaleString() + ' and post to the Master Sheet?';

    Swal.fire({
      title: short > 0 ? 'Verify With Money Short?' : 'Verify & Post to Master Sheet?',
      text: confirmText,
      icon: short > 0 ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Yes, verify'
    }).then((result) => {
      if (result.isConfirmed) {
        $form.off('submit').submit();
      }
    });
  });

  $(document).on('click', '.view-handover-sales-btn', function() {
    const sales = JSON.parse($(this).attr('data-sales') || '[]');
    renderDayClosingSalesModal(sales, $(this).data('title'));
  });

  if (window.location.hash) {
    const $target = $(window.location.hash);
    if ($target.length) {
      $('html, body').animate({ scrollTop: $target.offset().top - 80 }, 400);
      $target.addClass('border border-primary');
    }
  }
});
</script>
@endif
@endsection
