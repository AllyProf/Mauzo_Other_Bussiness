@extends('layouts.app')

@section('title', 'Services')

@section('styles')
<style>
  .business-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; max-height: 320px; overflow-y: auto; }
  .business-type-card { border: 2px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; background: #fff; min-height: 88px; }
  .business-type-card:hover, .business-type-card.selected { border-color: #940000; background: #fff5f5; }
  .business-type-card.imported { border-color: #28a745; background: #f6fff8; }
  .business-type-card.selected { background: #940000; color: #fff; }
  .business-type-card.selected .small { color: rgba(255,255,255,.85) !important; }
</style>
@endsection

@section('content')
@php
  $importedKeys = collect($importedTypes)->pluck('key')->all();
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-briefcase"></i> Services</h1>
    <p>Choose service business templates, import categories, set prices, then sell from Service POS</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
    <li class="breadcrumb-item">Services</li>
  </ul>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if(session('warning'))
<div class="alert alert-warning">{{ session('warning') }}</div>
@endif

@can('add_items')
<div class="tile mb-3">
  <h3 class="tile-title">Import service business template</h3>
  <p class="text-muted small">Select templates such as Print & Copy, Cyber Cafe, or Salon. Categories and default service prices are created automatically — adjust prices in the table below.</p>
  <form id="serviceTemplateForm" action="{{ route('services.import-templates') }}" method="POST">
    @csrf
    <div id="serviceTemplateTypesHidden"></div>
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
    <div class="business-type-grid mb-3">
      @foreach($serviceTemplates as $key => $template)
        @php $isImported = in_array($key, $importedKeys, true); @endphp
        <div class="business-type-card {{ $isImported ? 'imported' : '' }}" data-type="{{ $key }}">
          <i class="fa {{ $template['icon'] ?? 'fa-briefcase' }}"></i>
          <div class="font-weight-bold small">{{ $template['label'] }}</div>
          <div class="small text-muted">{{ count($template['categories'] ?? []) }} categories</div>
          @if($isImported)<div class="small text-success"><i class="fa fa-check"></i> Imported</div>@endif
        </div>
      @endforeach
    </div>
    <button type="button" class="btn btn-primary" id="btnImportServices" disabled><i class="fa fa-magic"></i> Import selected template(s)</button>
  </form>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Custom service business</h3>
  <p class="text-muted small mb-2">Define your own business name and categories. Optionally add services (one per line):<br>
    <code>Category | Service name | per page | 100</code></p>
  <form method="POST" action="{{ route('services.import-templates') }}">
    @csrf
    <input type="hidden" name="template_type" value="custom">
    @if($canPickBranch ?? false)
    <select name="branch_id" class="form-control mb-2" required>
      @foreach($writableBranches as $b)
        <option value="{{ $b->id }}">{{ $b->name }}</option>
      @endforeach
    </select>
    @else
      <input type="hidden" name="branch_id" value="{{ $writableBranches->first()?->id ?? Auth::user()->branch_id }}">
    @endif
    <div class="row">
      <div class="col-md-4">
        <input class="form-control" name="custom_business_name" placeholder="Business name e.g. Quick Print" required>
      </div>
      <div class="col-md-4">
        <input class="form-control" name="custom_categories" placeholder="Categories: Printing, Scanning" required>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary btn-block" type="submit">Import custom</button>
      </div>
    </div>
    <textarea class="form-control mt-2" name="custom_services" rows="4" placeholder="Printing | A4 B&W | per page | 100&#10;Printing | Lamination A4 | per sheet | 1000"></textarea>
  </form>
</div>
@endcan

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="tile-title mb-0">Service catalog & prices</h3>
        @can('process_sales')
        <div class="btn-group">
          <a href="{{ route('service-pos.create') }}" class="btn btn-success"><i class="fa fa-desktop"></i> Service POS</a>
          @if(plan_feature('invoices'))
          <a href="{{ route('service-invoices.create') }}" class="btn btn-outline-primary"><i class="fa fa-file-text-o"></i> New Invoice</a>
          <a href="{{ route('service-invoices.index') }}" class="btn btn-outline-secondary"><i class="fa fa-list"></i> Invoices</a>
          @endif
        </div>
        @endcan
      </div>

      @if($services->isEmpty())
        <p class="text-muted mb-0">No services yet. Import a template above to get started.</p>
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
                  <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editService{{ $service->id }}">Edit price</button>
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
                <p class="small text-muted mb-2">Optional: link a stock item to deduct when this service is sold (e.g. paper per page).</p>
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
                  <small class="text-muted">Example: 1 page print = 1 piece of A4 paper</small>
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
  </div>
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

@section('scripts')
<script>
(function () {
  const cards = document.querySelectorAll('.business-type-card[data-type]');
  const selected = new Set();
  const hidden = document.getElementById('serviceTemplateTypesHidden');
  const btn = document.getElementById('btnImportServices');
  const form = document.getElementById('serviceTemplateForm');

  function syncHidden() {
    hidden.innerHTML = '';
    selected.forEach(function (key) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'template_types[]';
      input.value = key;
      hidden.appendChild(input);
    });
    if (btn) btn.disabled = selected.size === 0;
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () {
      const key = card.getAttribute('data-type');
      if (selected.has(key)) { selected.delete(key); card.classList.remove('selected'); }
      else { selected.add(key); card.classList.add('selected'); }
      syncHidden();
    });
  });

  if (btn && form) {
    btn.addEventListener('click', function () {
      if (selected.size === 0) return;
      if (confirm('Import selected service templates for this branch?')) form.submit();
    });
  }
})();
</script>
@endsection
