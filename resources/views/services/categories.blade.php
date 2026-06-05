@extends('layouts.app')

@section('title', 'Service Categories')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-folder-open"></i> Service Categories & Catalog</h1>
    <p>Manage categories, service prices, and what appears on Service POS</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('services.register') }}">Services</a></li>
    <li class="breadcrumb-item">Categories</li>
  </ul>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($branchFilterId ?? null)
<div class="alert alert-info py-2">Viewing branch: <strong>{{ $activeBranchName }}</strong></div>
@endif

<div class="row mb-3">
  <div class="col-md-12">
    <div class="btn-group">
      @can('add_items')
      <a href="{{ route('services.register') }}" class="btn btn-outline-primary"><i class="fa fa-plus-circle"></i> Register Business</a>
      @endcan
      @can('process_sales')
      <a href="{{ route('service-pos.create') }}" class="btn btn-success"><i class="fa fa-desktop"></i> Service POS</a>
      @endcan
    </div>
  </div>
</div>

@canany(['manage_categories', 'add_items'])
@if(count($importedTypes) > 0)
<div class="tile mb-3">
  <h3 class="tile-title">Add category</h3>
  <form method="POST" action="{{ route('services.categories.store') }}" class="mb-0">
    @csrf
    @if($canPickBranch ?? false)
    <input type="hidden" name="branch_id" value="{{ $branchFilterId ?? $writableBranches->first()?->id }}">
    @else
    <input type="hidden" name="branch_id" value="{{ $writableBranches->first()?->id ?? Auth::user()->branch_id }}">
    @endif
    <div class="row">
      <div class="col-md-4">
        <input class="form-control" name="name" placeholder="Category name" required>
      </div>
      <div class="col-md-4">
        <select name="source_service_type_key" class="form-control" required>
          @foreach($importedTypes as $type)
            <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Add category</button>
      </div>
    </div>
  </form>
</div>
@else
<div class="alert alert-warning">
  <a href="{{ route('services.register') }}">Register a service business</a> first before adding categories.
</div>
@endif
@endcanany

<div class="tile mb-3">
  <h3 class="tile-title">Categories</h3>
  @if($categories->isEmpty())
    <p class="text-muted mb-0">No categories yet. Import a template from Register Business.</p>
  @else
    <div class="table-responsive">
      <table class="table table-hover table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('tables.columns.category') }}</th>
            <th>Business type</th>
            <th>{{ __('tables.columns.branch') }}</th>
            <th class="text-center">Services</th>
          </tr>
        </thead>
        <tbody>
          @foreach($categories as $category)
          <tr>
            <td><strong>{{ $category->name }}</strong></td>
            <td>
              @php
                $typeKey = $category->source_service_type_key;
                $typeLabel = collect($importedTypes)->firstWhere('key', $typeKey)['label'] ?? $typeKey;
              @endphp
              {{ $typeLabel }}
            </td>
            <td>{{ $category->branch?->name ?? '—' }}</td>
            <td class="text-center">{{ $category->services_count ?? $category->services->count() }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="tile">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="tile-title mb-0">Service catalog & prices</h3>
    @can('process_sales')
    <a href="{{ route('service-pos.create') }}" class="btn btn-success btn-sm"><i class="fa fa-desktop"></i> Open POS</a>
    @endcan
  </div>

  @if($services->isEmpty())
    <p class="text-muted mb-0">No services yet. Import a template or add services below.</p>
  @else
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead>
          <tr>
            <th>Service</th>
            <th>{{ __('tables.columns.category') }}</th>
            <th>{{ __('tables.columns.unit') }}</th>
            <th class="text-right">Price (TZS)</th>
            <th>{{ __('tables.columns.status') }}</th>
            @canany(['manage_categories','edit_items'])<th></th>@endcanany
          </tr>
        </thead>
        <tbody>
          @foreach($services as $service)
          <tr>
            <td><strong>{{ $service->name }}</strong></td>
            <td>{{ $service->category?->name }}</td>
            <td>{{ $service->unit_label }}</td>
            <td class="text-right">{{ number_format((float)$service->price, 0) }}</td>
            <td>
              @if($service->is_active)<span class="badge badge-success">{{ __('tables.status.active') }}</span>
              @else<span class="badge badge-secondary">{{ __('tables.status.inactive') }}</span>@endif
            </td>
            @canany(['manage_categories','edit_items'])
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editService{{ $service->id }}">Edit</button>
            </td>
            @endcanany
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @canany(['manage_categories','edit_items'])
    @foreach($services as $service)
    <div class="modal fade" id="editService{{ $service->id }}" tabindex="-1">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('services.update', $service) }}">
          @csrf @method('PUT')
          <div class="modal-header"><h5 class="modal-title">Edit {{ $service->name }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
          <div class="modal-body">
            <div class="form-group"><label>Name</label><input class="form-control" name="name" value="{{ $service->name }}" required></div>
            <div class="form-group"><label>Unit (e.g. per page)</label><input class="form-control" name="unit_label" value="{{ $service->unit_label }}" required></div>
            <div class="form-group"><label>Price TZS</label><input class="form-control" type="number" step="1" min="0" name="price" value="{{ (float)$service->price }}" required></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" {{ $service->is_active ? 'checked' : '' }}><label class="form-check-label">Active on POS</label></div>
            <hr>
            <p class="small text-muted mb-2">Optional: link a stock item to deduct when this service is sold.</p>
            <div class="form-group">
              <label>Consumable item</label>
              <select class="form-control" name="consumable_item_id">
                <option value="">None</option>
                @foreach($consumableItems ?? [] as $item)
                <option value="{{ $item->id }}" {{ (int)$service->consumable_item_id === (int)$item->id ? 'selected' : '' }}>{{ $item->name }} @if($item->sku)({{ $item->sku }})@endif</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label>Stock pieces per 1 service unit</label>
              <input class="form-control" type="number" step="0.0001" min="0" name="consumable_units_per_unit" value="{{ (float)($service->consumable_units_per_unit ?? 0) }}">
            </div>
          </div>
          <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
      </div>
    </div>
    @endforeach
    @endcanany
  @endif
</div>

@can('add_items')
@if(count($importedTypes) > 0)
<div class="tile">
  <h3 class="tile-title">Add custom service</h3>
  <form method="POST" action="{{ route('services.store') }}">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branchFilterId ?? $writableBranches->first()?->id }}">
    <div class="row">
      <div class="col-md-3">
        <select name="service_category_id" class="form-control" required>
          @foreach($categories as $cat)
            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3"><input class="form-control" name="name" placeholder="Service name" required></div>
      <div class="col-md-2"><input class="form-control" name="unit_label" value="per service" required></div>
      <div class="col-md-2"><input class="form-control" type="number" name="price" min="0" placeholder="Price" required></div>
      <div class="col-md-2"><button class="btn btn-primary btn-block">Add</button></div>
    </div>
  </form>
</div>
@endif
@endcan
@endsection
