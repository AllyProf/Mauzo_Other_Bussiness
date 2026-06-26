@extends('layouts.app')

@section('title', 'Industry Insights - Software Owner')

@section('styles')
<style>
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
  .stat-card-primary  { background: linear-gradient(135deg, #940000 0%, #c0392b 100%); }
  .stat-card-success  { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }
  .stat-card-warning  { background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%); }
  .stat-card-info     { background: linear-gradient(135deg, #2980b9 0%, #3498db 100%); }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-industry"></i> Industry Insights & Analytics</h1>
    <p>Compare performance, sales volumes, and revenues across different business categories.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Industry Insights</li>
  </ul>
</div>

{{-- Navigation Tabs / Quick Links --}}
<div class="tile mb-3">
  <div class="tile-body py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <h4 class="mb-0 text-secondary" style="font-size:1.1rem;"><i class="fa fa-line-chart mr-1"></i> Sector Comparison</h4>
    <div class="d-flex" style="gap:8px;">
      <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-bar-chart mr-1"></i> Platform Overview</a>
      <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-money mr-1"></i> Payments</a>
      <a href="{{ route('admin.regional.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-map-marker mr-1"></i> Regional Reports</a>
    </div>
  </div>
</div>

{{-- Summary Stats cards --}}
<div class="row mb-3">
  <div class="col-md-3 mb-2">
    <div class="stat-card stat-card-primary">
      <div class="stat-body">
        <h3 class="stat-value">{{ number_format($totalBusinesses) }}</h3>
        <div class="stat-label">Total Registered Tenants</div>
      </div>
      <i class="fa fa-building stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="stat-card stat-card-info">
      <div class="stat-body">
        <h3 class="stat-value">{{ number_format($insights->count()) }}</h3>
        <div class="stat-label">Active Industry Sectors</div>
      </div>
      <i class="fa fa-tag stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="stat-card stat-card-success">
      <div class="stat-body">
        <h3 class="stat-value">TZS {{ number_format($insights->sum('total_revenue')) }}</h3>
        <div class="stat-label">Combined Sector Revenue</div>
      </div>
      <i class="fa fa-money stat-icon"></i>
    </div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="stat-card stat-card-warning">
      <div class="stat-body">
        <h3 class="stat-value">{{ number_format($insights->sum('sales_count')) }}</h3>
        <div class="stat-label">Total Tracked Sales</div>
      </div>
      <i class="fa fa-shopping-cart stat-icon"></i>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-table mr-1"></i> Category Breakdown</h3>
      <div class="tile-body">
        @if($insights->isEmpty())
          <div class="text-center py-5 text-muted">
            <i class="fa fa-industry fa-3x mb-3 d-block"></i>
            <p>No business category data found. Ensure businesses have operational categories assigned.</p>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-striped">
              <thead class="bg-dark text-white" style="background-color:#343a40 !important;">
                <tr>
                  <th style="width: 50px;">Icon</th>
                  <th>Business Category</th>
                  <th class="text-center">Active Businesses</th>
                  <th class="text-center">Sales Volume (Orders)</th>
                  <th class="text-right">Accumulated Revenue</th>
                  <th class="text-right">Average Order Value</th>
                  <th class="text-center" style="width: 250px;">Market Revenue Share</th>
                </tr>
              </thead>
              <tbody>
                @php
                  $globalTotalRevenue = max(1, $insights->sum('total_revenue'));
                @endphp
                @foreach($insights as $row)
                  @php
                    $sharePercent = round(($row['total_revenue'] / $globalTotalRevenue) * 100, 1);
                  @endphp
                  <tr>
                    <td class="text-center"><i class="fa {{ $row['icon'] }} fa-lg text-danger"></i></td>
                    <td><strong>{{ $row['label'] }}</strong> <span class="badge badge-light"><code>{{ $row['key'] }}</code></span></td>
                    <td class="text-center">{{ number_format($row['business_count']) }}</td>
                    <td class="text-center">{{ number_format($row['sales_count']) }}</td>
                    <td class="text-right font-weight-bold">TZS {{ number_format($row['total_revenue']) }}</td>
                    <td class="text-right text-secondary">TZS {{ number_format($row['avg_ticket']) }}</td>
                    <td class="align-middle">
                      <div class="d-flex align-items-center" style="gap:10px;">
                        <div class="progress flex-grow-1" style="height: 10px;">
                          <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $sharePercent }}%;" aria-valuenow="{{ $sharePercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="small font-weight-bold">{{ $sharePercent }}%</span>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
