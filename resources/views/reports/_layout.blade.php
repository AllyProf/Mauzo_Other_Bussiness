@extends('layouts.app')

@section('title', ($title ?? 'Reports') . ' - SpareParts POS')

@section('styles')
<style>
  .report-table thead th {
    background-color: #940000 !important;
    color: #fff !important;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    vertical-align: middle !important;
    border-color: #7a0000 !important;
  }
  .report-table td { vertical-align: middle !important; }
  .money-col { text-align: right; font-family: 'Courier New', Courier, monospace; white-space: nowrap; }
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff !important; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000 !important; }
  .business-type-tab i { margin-right: 5px; }
  .report-chart-row > [class*="col-"] { display: flex; }
  .report-chart-row .tile { width: 100%; }
  .report-chart-wrap { position: relative; width: 100%; }
  .report-chart-wrap canvas {
    display: block;
    width: 100% !important;
    height: 100% !important;
  }
  .report-chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    margin-bottom: 10px;
  }
  .report-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #495057;
  }
  .report-legend-mark { flex-shrink: 0; }
  .report-legend-line {
    width: 24px;
    height: 3px;
    background: var(--legend-color);
    border-radius: 2px;
    position: relative;
  }
  .report-legend-line::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--legend-color);
    border: 1px solid #fff;
    box-shadow: 0 0 0 1px var(--legend-color);
  }
  .report-legend-bar {
    width: 14px;
    height: 14px;
    background: var(--legend-color);
    border-radius: 2px;
  }
  .report-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
  }
  .report-table-footer .pagination { margin-bottom: 0; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-bar-chart"></i> {{ $title ?? 'Report' }}</h1>
    <p>{{ $business->name ?? Auth::user()->business?->name }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">{{ $title ?? 'Report' }}</li>
  </ul>
</div>

@include('reports.partials.business-type-tabs')

@if($viewingAllBranches ?? false)
<div class="alert alert-light border py-2 mb-3">
  <i class="fa fa-building"></i>
  Viewing reports from <strong>all branches</strong>. Switch branch in the header to filter by location.
</div>
@elseif(!empty($activeBranchName))
<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-map-marker"></i>
  Reports for branch <strong>{{ $activeBranchName }}</strong> — sales filtered by item categories in this branch.
</div>
@endif

@yield('report-content')
@endsection

@section('scripts')
<script type="text/javascript" src="{{ asset('panel-assets/js/plugins/chart.js') }}"></script>
@yield('report-scripts')
@endsection
