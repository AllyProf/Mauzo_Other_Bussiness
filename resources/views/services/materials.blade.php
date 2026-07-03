@extends('layouts.app')

@section('title', 'Service Materials')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-cubes"></i> Service Materials Stock</h1>
    <p>Paper, ink, film and other supplies — separate from shop inventory. Stock deducts when service sales are paid.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('services.categories') }}">Services</a></li>
    <li class="breadcrumb-item">Materials</li>
  </ul>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
  <div class="col-md-12">
    <a href="{{ route('services.categories') }}" class="btn btn-outline-secondary"><i class="fa fa-folder-open"></i> Categories</a>
    <a href="{{ route('services.register') }}" class="btn btn-outline-primary ml-1"><i class="fa fa-plus-circle"></i> Register Business</a>
  </div>
</div>

@canany(['manage_services', 'manage_categories', 'add_items'])
<div class="tile mb-3">
  <h3 class="tile-title">Add material</h3>
  <form method="POST" action="{{ route('services.materials.store') }}">
    @csrf
    @if($canPickBranch ?? false)
    <div class="form-group">
      <label>Branch</label>
      <select name="branch_id" class="form-control" required>
        @foreach($writableBranches as $b)
          <option value="{{ $b->id }}" {{ ($branchFilterId ?? null) == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
        @endforeach
      </select>
    </div>
    @else
      <input type="hidden" name="branch_id" value="{{ $writableBranches->first()?->id ?? Auth::user()->branch_id }}">
    @endif
    <div class="row">
      <div class="col-md-5">
        <input class="form-control" name="name" placeholder="e.g. A4 Paper" required>
      </div>
      <div class="col-md-4">
        <input class="form-control" name="unit_label" value="sheet" placeholder="Unit e.g. sheet, ream, ml" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Add material</button>
      </div>
    </div>
  </form>
</div>
@endcanany

<div class="tile">
  <h3 class="tile-title">Materials stock
    @if($activeBranchName)
      <small class="text-muted">· {{ $activeBranchName }}</small>
    @endif
  </h3>

  @if($materials->isEmpty())
    <p class="text-muted mb-0">No materials yet. Add A4 Paper, lamination film, or other supplies your services consume.</p>
  @else
    <div class="table-responsive">
      <table class="table table-hover table-sm">
        <thead class="thead-light">
          <tr>
            <th>Material</th>
            <th>Unit</th>
            <th class="text-right">In stock</th>
            <th class="text-right">Last cost / unit</th>
            <th>Purchases</th>
            @canany(['manage_services', 'manage_categories', 'add_items', 'receive_stock'])
            <th></th>
            @endcanany
          </tr>
        </thead>
        <tbody>
          @foreach($materials as $material)
          <tr>
            <td><strong>{{ $material->name }}</strong></td>
            <td>{{ $material->unit_label }}</td>
            <td class="text-right">{{ $material->stockLabel() }}</td>
            <td class="text-right">
              @if($material->last_cost_per_unit)
                TZS {{ number_format((float)$material->last_cost_per_unit, 2) }}
              @else
                —
              @endif
            </td>
            <td>{{ $material->receipts_count }} receipt(s)</td>
            @canany(['manage_services', 'manage_categories', 'add_items', 'receive_stock'])
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#receiveMaterial{{ $material->id }}">
                <i class="fa fa-truck"></i> Receive stock
              </button>
            </td>
            @endcanany
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@canany(['manage_services', 'manage_categories', 'add_items', 'receive_stock'])
@foreach($materials as $material)
<div class="modal fade" id="receiveMaterial{{ $material->id }}" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('services.materials.receive', $material) }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Receive {{ $material->name }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">Current stock: <strong>{{ $material->stockLabel() }}</strong></p>
        <div class="form-group">
          <label>Quantity ({{ $material->unit_label }})</label>
          <input class="form-control" type="number" step="0.01" min="0.01" name="quantity" required placeholder="e.g. 5000">
        </div>
        <div class="form-group">
          <label>Total cost paid (TZS)</label>
          <input class="form-control" type="number" step="1" min="0" name="total_cost" placeholder="What you paid for this batch">
        </div>
        <div class="form-group">
          <label>Date received</label>
          <input class="form-control" type="date" name="received_date" value="{{ date('Y-m-d') }}" required>
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <input class="form-control" name="notes" placeholder="e.g. Supplier name">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Record purchase</button>
      </div>
    </form>
  </div>
</div>
@endforeach
@endcanany

<div class="tile mt-3 bg-light">
  <h5><i class="fa fa-info-circle"></i> How it works</h5>
  <ol class="mb-0 small">
    <li>Add materials here (paper, film, toner, etc.)</li>
    <li>Record purchases with <strong>Receive stock</strong></li>
    <li>On <a href="{{ route('services.categories') }}">Categories</a>, edit each service and link a material + usage per sale</li>
    <li>When a service is sold and paid, stock deducts automatically</li>
  </ol>
</div>
@endsection
