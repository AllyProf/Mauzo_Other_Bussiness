@extends('layouts.app')

@section('title', 'Service Categories')

@section('styles')
<style>
  .category-group { border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 1rem; overflow: hidden; }
  .category-group-header {
    background: #f8f9fa;
    padding: 12px 16px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }
  .category-group-header h4 { margin: 0; font-size: 1rem; font-weight: 700; }
  .category-group-body { padding: 0; }
  .category-group-body .table { margin-bottom: 0; }
  .category-group-body .table td, .category-group-body .table th { vertical-align: middle; }
  .add-service-row { background: #fcfcfc; padding: 12px 16px; border-top: 1px solid #e9ecef; }
  .add-service-row .form-control { font-size: 0.875rem; }
  .service-empty { padding: 16px; color: #6c757d; font-size: 0.9rem; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-folder-open"></i> Service Categories & Catalog</h1>
    <p>Each category can have many services with different prices (e.g. Printing → B&amp;W, Colour)</p>
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
        <input class="form-control" name="name" placeholder="Category name e.g. Printing" required>
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
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <h3 class="tile-title mb-0">Categories &amp; services</h3>
    @can('process_sales')
    <a href="{{ route('service-pos.create') }}" class="btn btn-success btn-sm"><i class="fa fa-desktop"></i> Open POS</a>
    @endcan
  </div>

  @if($categories->isEmpty())
    <p class="text-muted mb-0">No categories yet. Import a template from Register Business — e.g. Print &amp; Copy creates a <strong>Printing</strong> category with B&amp;W and Colour services.</p>
  @else
    @foreach($categories as $category)
      @php
        $typeKey = $category->source_service_type_key;
        $typeLabel = collect($importedTypes)->firstWhere('key', $typeKey)['label'] ?? $typeKey;
        $categoryServices = $services->where('service_category_id', $category->id);
      @endphp
      <div class="category-group">
        <div class="category-group-header">
          <div>
            <h4><i class="fa fa-folder-open text-muted"></i> {{ $category->name }}</h4>
            <small class="text-muted">{{ $typeLabel }} · {{ $category->branch?->name ?? '—' }}</small>
          </div>
          <span class="badge badge-info">{{ $categoryServices->count() }} service(s)</span>
        </div>
        <div class="category-group-body">
          @if($categoryServices->isEmpty())
            <div class="service-empty">No services in this category yet. Add variants below (e.g. Black &amp; White, Colour).</div>
          @else
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>Service</th>
                    <th>{{ __('tables.columns.unit') }}</th>
                    <th class="text-right">Price (TZS)</th>
                    <th>{{ __('tables.columns.status') }}</th>
                    @canany(['manage_categories','edit_items'])<th></th>@endcanany
                  </tr>
                </thead>
                <tbody>
                  @foreach($categoryServices as $service)
                  <tr>
                    <td><strong>{{ $service->name }}</strong></td>
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
          @endif

          @can('add_items')
          <div class="add-service-row">
            <form method="POST" action="{{ route('services.store') }}" class="mb-0">
              @csrf
              <input type="hidden" name="branch_id" value="{{ $category->branch_id ?? ($branchFilterId ?? $writableBranches->first()?->id) }}">
              <input type="hidden" name="service_category_id" value="{{ $category->id }}">
              <div class="row align-items-end">
                <div class="col-md-4 col-12 mb-2 mb-md-0">
                  <label class="small text-muted mb-1">Service name</label>
                  <input class="form-control" name="name" placeholder="e.g. A4 Black &amp; White" required>
                </div>
                <div class="col-md-3 col-6 mb-2 mb-md-0">
                  <label class="small text-muted mb-1">Unit</label>
                  <input class="form-control" name="unit_label" value="per page" required>
                </div>
                <div class="col-md-2 col-6 mb-2 mb-md-0">
                  <label class="small text-muted mb-1">Price TZS</label>
                  <input class="form-control" type="number" name="price" min="0" step="1" placeholder="100" required>
                </div>
                <div class="col-md-3 col-12">
                  <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Add to {{ $category->name }}</button>
                </div>
              </div>
            </form>
          </div>
          @endcan
        </div>
      </div>
    @endforeach
  @endif
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
@endsection
