@extends('layouts.app')

@section('title', 'Platform Reports - Admin')

@section('styles')
<style>
  .report-chart-row > [class*="col-"] { display: flex; }
  .report-chart-row .tile { width: 100%; }
  .report-chart-wrap { position: relative; width: 100%; height: 300px; }
  .report-chart-wrap canvas { width: 100% !important; height: 100% !important; }

  /* ── Uniform summary stat cards ─────────────────────── */
  .stat-card {
    border-radius: 10px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    min-height: 100px;
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.14);
  }
  .stat-card .stat-icon {
    font-size: 2.4rem;
    opacity: 0.22;
    position: absolute;
    right: 18px;
    bottom: 10px;
  }
  .stat-card .stat-body { flex: 1; }
  .stat-card .stat-value { font-size: 1.55rem; font-weight: 700; line-height: 1.1; margin: 0; }
  .stat-card .stat-label { font-size: 0.82rem; opacity: 0.85; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-card .stat-sub   { font-size: 0.8rem; opacity: 0.75; margin-top: 4px; }
  .stat-card-primary  { background: linear-gradient(135deg, #940000 0%, #c0392b 100%); }
  .stat-card-success  { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }
  .stat-card-warning  { background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%); }
  .stat-card-info     { background: linear-gradient(135deg, #2980b9 0%, #3498db 100%); }

  /* ── Chart tile header ──────────────────────────────── */
  .tile .tile-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #444;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 10px;
    margin-bottom: 0;
  }
  .tile .tile-title i { color: #940000; }

  /* ── Period selector bar ─────────────────────────────── */
  .period-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .period-bar a {
    display: inline-block;
    padding: 5px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1px solid #dee2e6;
    color: #555;
    text-decoration: none;
    transition: all 0.2s;
  }
  .period-bar a.active, .period-bar a:hover {
    background-color: #940000;
    color: #fff;
    border-color: #940000;
  }
</style>
@endsection

@section('content')
@php
  $summary = $data['summary'];
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-bar-chart"></i> Platform Reports</h1>
    <p>Analytics across all businesses, payments, and support.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Reports</a></li>
  </ul>
</div>

{{-- Period + Quick Links bar --}}
<div class="tile mb-3">
  <div class="tile-body py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
      <div>
        <span class="text-muted font-weight-bold mr-2" style="font-size:0.9rem;">PERIOD:</span>
        <div class="period-bar d-inline-flex">
          <a href="{{ route('admin.reports.index', ['months'=>3]) }}" class="{{ $months == 3 ? 'active' : '' }}">3 months</a>
          <a href="{{ route('admin.reports.index', ['months'=>6]) }}" class="{{ $months == 6 ? 'active' : '' }}">6 months</a>
          <a href="{{ route('admin.reports.index', ['months'=>12]) }}" class="{{ $months == 12 ? 'active' : '' }}">12 months</a>
        </div>
      </div>
      <div class="d-flex" style="gap:8px;">
        <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-money mr-1"></i> Payments</a>
        <a href="{{ route('admin.reports.industry-insights') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-industry mr-1"></i> Industry Insights</a>
        <a href="{{ route('admin.regional.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-map-marker mr-1"></i> Regional</a>
      </div>
    </div>
  </div>
</div>

{{-- ── Uniform Stat Cards ── --}}
<div class="row mb-3">
  <div class="col-md-3 col-sm-6 mb-3">
    <div class="stat-card stat-card-primary">
      <div class="stat-body">
        <div class="stat-label">Total Businesses</div>
        <div class="stat-value">{{ number_format($summary['total_businesses']) }}</div>
        <div class="stat-sub">{{ $summary['active_businesses'] }} active &middot; {{ $summary['total_businesses'] - $summary['active_businesses'] }} inactive</div>
      </div>
      <i class="fa fa-building stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-sm-6 mb-3">
    <div class="stat-card stat-card-success">
      <div class="stat-body">
        <div class="stat-label">Total Collected</div>
        <div class="stat-value">TZS {{ number_format($summary['total_collected'], 0) }}</div>
        <div class="stat-sub">All time platform revenue</div>
      </div>
      <i class="fa fa-money stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-sm-6 mb-3">
    <div class="stat-card stat-card-warning">
      <div class="stat-body">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value">TZS {{ number_format($summary['outstanding'], 0) }}</div>
        <div class="stat-sub">Unpaid invoices pending</div>
      </div>
      <i class="fa fa-clock-o stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-sm-6 mb-3">
    <div class="stat-card stat-card-info">
      <div class="stat-body">
        <div class="stat-label">This Month</div>
        <div class="stat-value">TZS {{ number_format($summary['collected_this_month'], 0) }}</div>
        <div class="stat-sub">Revenue collected {{ now()->format('M Y') }}</div>
      </div>
      <i class="fa fa-calendar stat-icon"></i>
    </div>
  </div>
</div>

{{-- ── Revenue Trend + Invoice Status ── --}}
<div class="row report-chart-row mb-3">
  <div class="col-lg-8 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-line-chart"></i> Subscription Revenue Trend</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="revenueTrendChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-pie-chart"></i> Invoice Status</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="invoiceStatusChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

{{-- ── Business Status + Plan + Support ── --}}
<div class="row report-chart-row mb-3">
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-pie-chart"></i> Business Status</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="businessStatusChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-credit-card"></i> Plan Distribution</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="planDistributionChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-ticket"></i> Support Tickets</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="supportTicketsChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

{{-- ── Registrations + Top Businesses ── --}}
<div class="row report-chart-row mb-3">
  <div class="col-lg-6 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-area-chart"></i> New Business Registrations</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="registrationsChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title px-3 pt-3"><i class="fa fa-bar-chart"></i> Top Paying Businesses</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="topBusinessesChart"></canvas></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function () {
  const brand = '#940000';
  const palette = ['#940000', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997', '#6c757d'];

  const monthlyRevenue      = @json($data['monthlyRevenue']);
  const businessStatus      = @json($data['businessStatus']);
  const registrationsTrend  = @json($data['registrationsTrend']);
  const invoiceStatus       = @json($data['invoiceStatus']);
  const topBusinesses       = @json($data['topBusinesses']);
  const planDistribution    = @json($data['planDistribution']);
  const supportTickets      = @json($data['supportTickets']);

  const defaultOpts = { responsive: true, maintainAspectRatio: false };
  const monthLabels = monthlyRevenue.map(r => r.label);

  new Chart(document.getElementById('revenueTrendChart'), {
    type: 'bar',
    data: {
      labels: monthLabels,
      datasets: [
        { label: 'Invoiced (TZS)',    data: monthlyRevenue.map(r => r.invoiced),    backgroundColor: 'rgba(148,0,0,0.75)',    borderRadius: 4 },
        { label: 'Paid (TZS)',        data: monthlyRevenue.map(r => r.paid),        backgroundColor: 'rgba(40,167,69,0.85)', borderRadius: 4 },
        { label: 'Outstanding (TZS)', data: monthlyRevenue.map(r => r.outstanding), backgroundColor: 'rgba(255,193,7,0.85)', borderRadius: 4 },
      ],
    },
    options: { ...defaultOpts, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'TZS ' + Number(v).toLocaleString() } } } },
  });

  new Chart(document.getElementById('invoiceStatusChart'), {
    type: 'doughnut',
    data: { labels: invoiceStatus.map(r => r.label), datasets: [{ data: invoiceStatus.map(r => r.amount), backgroundColor: ['#28a745','#17a2b8','#ffc107'] }] },
    options: { ...defaultOpts, plugins: { legend: { position: 'bottom' } } },
  });

  new Chart(document.getElementById('businessStatusChart'), {
    type: 'doughnut',
    data: { labels: businessStatus.map(r => r.label), datasets: [{ data: businessStatus.map(r => r.count), backgroundColor: ['#28a745','#dc3545','#6c757d','#ffc107'] }] },
    options: { ...defaultOpts, plugins: { legend: { position: 'bottom' } } },
  });

  new Chart(document.getElementById('planDistributionChart'), {
    type: 'pie',
    data: { labels: planDistribution.map(r => r.label), datasets: [{ data: planDistribution.map(r => r.count), backgroundColor: palette }] },
    options: { ...defaultOpts, plugins: { legend: { position: 'bottom' } } },
  });

  new Chart(document.getElementById('supportTicketsChart'), {
    type: 'bar',
    data: { labels: supportTickets.map(r => r.label), datasets: [{ label: 'Tickets', data: supportTickets.map(r => r.count), backgroundColor: palette, borderRadius: 4 }] },
    options: { ...defaultOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('registrationsChart'), {
    type: 'line',
    data: { labels: registrationsTrend.map(r => r.label), datasets: [{ label: 'New Registrations', data: registrationsTrend.map(r => r.count), borderColor: brand, backgroundColor: 'rgba(148,0,0,0.12)', fill: true, tension: 0.3, pointRadius: 4 }] },
    options: { ...defaultOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('topBusinessesChart'), {
    type: 'bar',
    data: { labels: topBusinesses.map(r => r.business_name), datasets: [{ label: 'Total Paid (TZS)', data: topBusinesses.map(r => r.total_paid), backgroundColor: 'rgba(148,0,0,0.8)', borderRadius: 4 }] },
    options: { ...defaultOpts, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => 'TZS ' + Number(v).toLocaleString() } } } },
  });
})();
</script>
@endpush
