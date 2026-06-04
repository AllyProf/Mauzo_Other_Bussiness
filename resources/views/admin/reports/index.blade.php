@extends('layouts.app')

@section('title', 'Platform Reports - Admin')

@section('styles')
<style>
  .report-chart-row > [class*="col-"] { display: flex; }
  .report-chart-row .tile { width: 100%; }
  .report-chart-wrap { position: relative; width: 100%; height: 300px; }
  .report-chart-wrap canvas { width: 100% !important; height: 100% !important; }
</style>
@endsection

@section('content')
@php
  $summary = $data['summary'];
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-bar-chart"></i> Platform Reports</h1>
    <p>Charts and analytics across all businesses, payments, and support.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Reports</a></li>
  </ul>
</div>

<form method="GET" action="{{ route('admin.reports.index') }}" class="tile mb-3">
  <div class="tile-body py-3">
    <div class="row align-items-end">
      <div class="col-md-3 form-group mb-md-0">
        <label class="control-label">Chart Period</label>
        <select name="months" class="form-control" onchange="this.form.submit()">
          <option value="3" {{ $months == 3 ? 'selected' : '' }}>Last 3 months</option>
          <option value="6" {{ $months == 6 ? 'selected' : '' }}>Last 6 months</option>
          <option value="12" {{ $months == 12 ? 'selected' : '' }}>Last 12 months</option>
        </select>
      </div>
      <div class="col-md-9 text-md-right">
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-money"></i> Payment Report</a>
      </div>
    </div>
  </div>
</form>

<div class="row mb-2">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-building fa-3x"></i>
      <div class="info"><h4>Businesses</h4><p><b>{{ number_format($summary['total_businesses']) }}</b><br><small>{{ $summary['active_businesses'] }} active</small></p></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-money fa-3x"></i>
      <div class="info"><h4>Total Collected</h4><p><b>TZS {{ number_format($summary['total_collected'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info"><h4>Outstanding</h4><p><b>TZS {{ number_format($summary['outstanding'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon"><i class="icon fa fa-calendar fa-3x"></i>
      <div class="info"><h4>This Month</h4><p><b>TZS {{ number_format($summary['collected_this_month'], 0) }}</b><br><small>collected</small></p></div>
    </div>
  </div>
</div>

<div class="row report-chart-row">
  <div class="col-lg-8 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-line-chart"></i> Subscription Revenue Trend</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="revenueTrendChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-pie-chart"></i> Invoice Status</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="invoiceStatusChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<div class="row report-chart-row">
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-pie-chart"></i> Business Status</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="businessStatusChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-credit-card"></i> Plan Distribution</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="planDistributionChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-ticket"></i> Support Tickets</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="supportTicketsChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<div class="row report-chart-row">
  <div class="col-lg-6 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-area-chart"></i> New Business Registrations</h3>
      <div class="tile-body">
        <div class="report-chart-wrap"><canvas id="registrationsChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 mb-3">
    <div class="tile mb-0">
      <h3 class="tile-title"><i class="fa fa-bar-chart"></i> Top Paying Businesses</h3>
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

  const monthlyRevenue = @json($data['monthlyRevenue']);
  const businessStatus = @json($data['businessStatus']);
  const registrationsTrend = @json($data['registrationsTrend']);
  const invoiceStatus = @json($data['invoiceStatus']);
  const topBusinesses = @json($data['topBusinesses']);
  const planDistribution = @json($data['planDistribution']);
  const supportTickets = @json($data['supportTickets']);

  const monthLabels = monthlyRevenue.map(r => r.label);

  new Chart(document.getElementById('revenueTrendChart'), {
    type: 'bar',
    data: {
      labels: monthLabels,
      datasets: [
        {
          label: 'Invoiced (TZS)',
          data: monthlyRevenue.map(r => r.invoiced),
          backgroundColor: 'rgba(148,0,0,0.75)',
          borderRadius: 4,
        },
        {
          label: 'Paid (TZS)',
          data: monthlyRevenue.map(r => r.paid),
          backgroundColor: 'rgba(40,167,69,0.85)',
          borderRadius: 4,
        },
        {
          label: 'Outstanding (TZS)',
          data: monthlyRevenue.map(r => r.outstanding),
          backgroundColor: 'rgba(255,193,7,0.85)',
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: value => 'TZS ' + Number(value).toLocaleString(),
          },
        },
      },
    },
  });

  new Chart(document.getElementById('invoiceStatusChart'), {
    type: 'doughnut',
    data: {
      labels: invoiceStatus.map(r => r.label),
      datasets: [{
        data: invoiceStatus.map(r => r.amount),
        backgroundColor: ['#28a745', '#17a2b8', '#ffc107'],
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
    },
  });

  new Chart(document.getElementById('businessStatusChart'), {
    type: 'doughnut',
    data: {
      labels: businessStatus.map(r => r.label),
      datasets: [{
        data: businessStatus.map(r => r.count),
        backgroundColor: ['#28a745', '#dc3545', '#6c757d', '#ffc107'],
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
    },
  });

  new Chart(document.getElementById('planDistributionChart'), {
    type: 'pie',
    data: {
      labels: planDistribution.map(r => r.label),
      datasets: [{
        data: planDistribution.map(r => r.count),
        backgroundColor: palette,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
    },
  });

  new Chart(document.getElementById('supportTicketsChart'), {
    type: 'bar',
    data: {
      labels: supportTickets.map(r => r.label),
      datasets: [{
        label: 'Tickets',
        data: supportTickets.map(r => r.count),
        backgroundColor: palette,
        borderRadius: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });

  new Chart(document.getElementById('registrationsChart'), {
    type: 'line',
    data: {
      labels: registrationsTrend.map(r => r.label),
      datasets: [{
        label: 'New Registrations',
        data: registrationsTrend.map(r => r.count),
        borderColor: brand,
        backgroundColor: 'rgba(148,0,0,0.12)',
        fill: true,
        tension: 0.3,
        pointRadius: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });

  new Chart(document.getElementById('topBusinessesChart'), {
    type: 'bar',
    data: {
      labels: topBusinesses.map(r => r.business_name),
      datasets: [{
        label: 'Total Paid (TZS)',
        data: topBusinesses.map(r => r.total_paid),
        backgroundColor: 'rgba(148,0,0,0.8)',
        borderRadius: 4,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            callback: value => 'TZS ' + Number(value).toLocaleString(),
          },
        },
      },
    },
  });
})();
</script>
@endpush
