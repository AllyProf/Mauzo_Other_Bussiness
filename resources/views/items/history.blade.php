@extends('layouts.app')

@section('title', 'Item History - ' . $item->name)

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> {{ $item->name }}</h1>
    <p>Stock movement history — receiving, sales, losses, and who handled each transaction</p>
  </div>
  <a href="{{ route('items.stock') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Stock</a>
</div>

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Total Received</h4>
        <p><b>{{ fmod($stats['total_received'], 1.0) === 0.0 ? (int) $stats['total_received'] : number_format($stats['total_received'], 2) }}</b> {{ $unitName }}(s)</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-arrow-up fa-3x"></i>
      <div class="info">
        <h4>Total Sold</h4>
        <p><b>{{ fmod($stats['total_sold'], 1.0) === 0.0 ? (int) $stats['total_sold'] : number_format($stats['total_sold'], 2) }}</b> {{ $unitName }}(s)</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-minus-circle fa-3x"></i>
      <div class="info">
        <h4>Total Lost</h4>
        <p><b>{{ fmod($stats['total_lost'] ?? 0, 1.0) === 0.0 ? (int) ($stats['total_lost'] ?? 0) : number_format($stats['total_lost'] ?? 0, 2) }}</b> {{ $unitName }}(s)</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>Current Stock</h4>
        <p><b>{{ fmod($stats['current_stock'], 1.0) === 0.0 ? (int) $stats['current_stock'] : number_format($stats['current_stock'], 2) }}</b> {{ $unitName }}(s)</p>
      </div>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body py-2">
        <strong>Category:</strong> {{ $item->category->name ?? 'N/A' }}
        &nbsp;|&nbsp;
        <strong>SKU:</strong> {{ $item->sku }}
        &nbsp;|&nbsp;
        <strong>Unit:</strong> {{ $unitName }}
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Movement History</h3>
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="historyTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.time') }}</th>
              <th>Type</th>
              <th>{{ __('tables.columns.reference') }}</th>
              <th>Qty Change</th>
              <th>By (Staff)</th>
              <th>Supplier / Customer</th>
              <th>{{ __('tables.columns.details') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movements as $movement)
              <tr>
                <td>{{ \Carbon\Carbon::parse($movement['date'])->format('M d, Y') }}</td>
                <td>{{ $movement['time'] ?: '—' }}</td>
                <td><span class="badge badge-{{ $movement['badge'] }}">{{ $movement['type_label'] }}</span></td>
                <td>
                  <a href="{{ $movement['reference_url'] }}">{{ $movement['reference'] }}</a>
                </td>
                <td class="font-weight-bold {{ $movement['quantity_class'] }}">{{ $movement['quantity_label'] }} {{ $movement['quantity_unit'] ?? $unitName }}(s)</td>
                <td>{{ $movement['by'] }}</td>
                <td>
                  <small class="text-muted d-block">{{ $movement['party_label'] }}</small>
                  {{ $movement['party'] }}
                </td>
                <td>{{ $movement['details'] }}</td>
                <td>{{ $movement['status'] }}</td>
              </tr>
            @endforeach
            @if($movements->isEmpty())
              <tr>
                <td colspan="9" class="text-center">No stock movements recorded for this item yet.</td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">
        $('#historyTable').DataTable({
            order: [[0, 'desc'], [1, 'desc']],
            pageLength: 25
        });
    </script>
@endsection
