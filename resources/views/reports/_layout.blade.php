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

  /* Equal-size statistical cards across report pages */
  .report-page .report-stat-widgets {
    display: flex;
    flex-wrap: wrap;
  }
  .report-page .report-stat-widgets > .report-stat-col {
    display: flex;
  }
  .report-page .report-stat-widgets .widget-small {
    display: flex;
    align-items: stretch;
    width: 100%;
    min-height: 72px;
    height: 100%;
    margin-bottom: 12px;
    border-radius: 8px !important;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  }
  .report-page .report-stat-widgets .widget-small .icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 56px;
    min-width: 56px !important;
    max-width: 56px;
    padding: 8px 6px !important;
    font-size: 1.35rem !important;
    border-radius: 8px 0 0 8px;
  }
  .report-page .report-stat-widgets .widget-small .icon.fa-3x {
    font-size: 1.35rem !important;
  }
  .report-page .report-stat-widgets .widget-small .info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    flex: 1 1 auto;
    min-width: 0;
    align-self: stretch;
    padding: 8px 12px;
  }
  .report-page .report-stat-widgets .widget-small .info h4 {
    margin: 0 0 3px;
    font-size: 0.65rem !important;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.25;
    min-height: 0;
    max-height: 2.5em;
    color: #6c757d;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .report-page .report-stat-widgets .widget-small .info p {
    margin: 0 !important;
    font-size: 0.88rem !important;
    font-weight: 700;
    line-height: 1.2;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  @media (max-width: 991.98px) {
    .report-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .report-page .app-title p { font-size: 0.88rem; }
    .report-page .business-type-tabs { padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
    .report-page .report-chart-row > [class*="col-"] { margin-bottom: 0; }
    .report-page .report-chart-wrap { min-height: 220px; }
    .report-page .tile-title-w-btn { flex-direction: column; align-items: flex-start !important; }
    .report-page .tile-title-w-btn .title { margin-bottom: 8px !important; }
    .report-page .report-table-footer { flex-direction: column; align-items: stretch; text-align: center; }
    .report-page .report-filter-form .form-group { margin-bottom: 0.75rem; }
    .report-page .report-stat-widgets .widget-small { min-height: 68px; }
    .report-page .report-stat-widgets .widget-small .icon {
      flex-basis: 48px;
      min-width: 48px !important;
      max-width: 48px;
      font-size: 1.2rem !important;
    }
    .report-page .report-stat-widgets .widget-small .icon.fa-3x { font-size: 1.2rem !important; }
    .report-page .report-stat-widgets .widget-small .info { padding: 7px 10px; }
    .report-page .report-stat-widgets .widget-small .info h4 { font-size: 0.62rem !important; }
    .report-page .report-stat-widgets .widget-small .info p { font-size: 0.82rem !important; }
    .report-page .report-mobile-card {
      border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
    }
    .report-page .report-mobile-head {
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #eee;
    }
    .report-page .report-mobile-head strong { color: #940000; }
    .report-page .report-mobile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 12px; }
    .report-page .report-mobile-stat span {
      display: block; font-size: 0.72rem; text-transform: uppercase; color: #6c757d;
      font-weight: 600; letter-spacing: 0.02em;
    }
    .report-page .report-mobile-stat strong {
      display: block; font-size: 0.9rem; margin-top: 2px; font-family: 'Courier New', Courier, monospace;
    }
  }

  @media (max-width: 575.98px) {
    .report-page .report-stat-widgets .widget-small { min-height: 64px; }
    .report-page .report-stat-widgets .widget-small .info p {
      white-space: normal;
      word-break: break-word;
      font-size: 0.78rem !important;
    }
  }

  @media (max-width: 767.98px) {
    .report-page .app-title h1 { font-size: 1.15rem; }
    .report-page .app-breadcrumb { font-size: 0.85rem; }
  }
</style>
@endsection

@section('content')
<div class="report-page">
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

@include('reports.partials.date-range-filter')

@yield('report-content')
</div>
@endsection

@section('scripts')
<script type="text/javascript" src="{{ asset('panel-assets/js/plugins/chart.js') }}"></script>
@yield('report-scripts')
@endsection
