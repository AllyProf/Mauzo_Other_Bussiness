@extends('layouts.app')

@section('title', 'Item Details - ' . $item->name)

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-info-circle"></i> Item Details</h1>
    <p>Comprehensive overview of {{ $item->name }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Items</li>
    <li class="breadcrumb-item"><a href="#">Details</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title">General Information</h3>
      <div class="tile-body">
        <table class="table table-sm">
            <tr>
                <th>Name:</th>
                <td>{{ $item->name }}</td>
            </tr>
            <tr>
                <th>SKU:</th>
                <td><span class="badge badge-dark">{{ $item->sku }}</span></td>
            </tr>
            <tr>
                <th>Category:</th>
                <td>{{ $item->category->name ?? 'Uncategorized' }}</td>
            </tr>
            <tr>
                <th>Brand:</th>
                <td>{{ $item->brand ?? 'N/A' }}</td>
            </tr>
        </table>
        <hr>
        <strong>Description:</strong>
        <p class="text-muted">{{ $item->description ?: 'No description provided.' }}</p>
      </div>
      <div class="tile-footer">
        @can('edit_items')
        <a class="btn btn-info" href="{{ route('items.edit', $item->id) }}"><i class="fa fa-edit"></i> Edit Item</a>
        @endcan
        <a class="btn btn-secondary" href="{{ route('items.index') }}"><i class="fa fa-arrow-left"></i> Back to List</a>
        <a class="btn btn-primary" href="{{ route('items.history', $item->id) }}"><i class="fa fa-history"></i> Stock History</a>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-archive"></i> Packaging & Unit Conversion</h3>
      <div class="tile-body">
        <div class="row text-center mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded">
                    <small class="text-uppercase font-weight-bold text-muted d-block">Received As</small>
                    <h4 class="mb-0 text-primary">{{ $item->receivingPackaging->name ?? 'N/A' }}</h4>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-center justify-content-center">
                <i class="fa fa-exchange fa-2x text-muted"></i>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded">
                    <small class="text-uppercase font-weight-bold text-muted d-block">Sold As</small>
                    <h4 class="mb-0 text-success">{{ $item->packagings->first()->packagingType->name ?? 'N/A' }}</h4>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Every <strong>1 {{ $item->receivingPackaging->name ?? 'unit' }}</strong> received adds
            <strong>{{ $item->units_per_receiving_pack ?? 1 }} piece(s)</strong> to inventory.
        </div>

        <table class="table table-bordered mt-4">
            <thead class="bg-light">
                <tr>
                    <th>Selling Unit</th>
                    <th>Contains (pieces)</th>
                    <th>Last Buying Price</th>
                    <th>Current Selling Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($item->packagings as $pkg)
                <tr>
                    <td><strong>{{ $pkg->packagingType->name }}</strong></td>
                    <td>{{ $pkg->quantity_per_unit }}</td>
                    <td>TZS {{ number_format($pkg->cost_price, 2) }}</td>
                    <td>TZS {{ number_format($pkg->selling_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-light">
                <tr>
                    <th colspan="2" class="text-right">Total pieces in stock:</th>
                    <th colspan="2">{{ fmod($item->current_stock, 1.0) === 0.0 ? (int) $item->current_stock : number_format($item->current_stock, 2) }}</th>
                </tr>
            </tfoot>
        </table>
        
        <p class="small text-muted mt-3">
            <i class="fa fa-clock-o"></i> Prices are set during <a href="{{ route('receivings.index') }}">Stock-In (Receiving)</a> when new stock arrives.
            @can('edit_items')
            To change prices later without receiving stock, use <a href="{{ route('items.edit', $item->id) }}">Edit Item</a>.
            @endcan
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
