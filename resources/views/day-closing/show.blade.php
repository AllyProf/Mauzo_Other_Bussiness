@extends('layouts.app')

@section('title', 'Daily Reconciliation Report')

@section('styles')
<style>
  #staff-table { border-collapse: collapse !important; }
  #staff-table thead th { background-color: #2d3436 !important; color: white !important; font-size: 0.75rem; text-transform: uppercase; }
  .audit-col-bg { background-color: #f1f7fe !important; }
  .diff-col-bg { background-color: #fff9f1 !important; }
  .status-pill { border-radius: 50px; padding: 4px 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
  .widget-small.coloured-icon .info,
  .widget-small.coloured-icon .info h4,
  .widget-small.coloured-icon .info p,
  .widget-small.coloured-icon .info b { color: #000 !important; }
  @media print { .app-title, .d-print-none, .app-sidebar, .app-header { display: none !important; } .app-content { margin-left: 0 !important; } }
</style>
@endsection

@section('content')
<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-balance-scale"></i> Handover Submitted</h1>
    <p>{{ $dayClosing->business->name ?? Auth::user()->business->name }} — {{ $dayClosing->closing_date->format('M d, Y') }}</p>
  </div>
  <div>
    <a href="{{ route('day-closing.index', ['date' => $dayClosing->closing_date->format('Y-m-d')]) }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Daily Reconciliation</a>
    <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
  </div>
</div>

@include('day-closing.partials.handover-card')

<div class="modal fade" id="salesModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">All Sales</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><div id="sales-content"></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
@include('day-closing.partials.sales-modal-render')
<script>
jQuery(function($) {
  $(document).on('click', '.view-handover-sales-btn', function() {
    const sales = JSON.parse($(this).attr('data-sales') || '[]');
    renderDayClosingSalesModal(sales, $(this).data('title') || 'All Sales');
  });
});
</script>
@endsection
