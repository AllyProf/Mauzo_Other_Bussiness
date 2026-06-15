@extends('layouts.app')

@section('title', 'Register Service Business')

@section('styles')
@include('services.partials.template-styles')
@endsection

@section('content')
@php
  $importedKeys = collect($importedTypes)->pluck('key')->all();
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-plus-circle"></i> Register Service Business</h1>
    <p>Import ready-made templates or define a custom service business for your branch</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('services.categories') }}">Services</a></li>
    <li class="breadcrumb-item">Register Business</li>
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
  <p class="text-muted small">Select templates such as Print & Copy, Cyber Cafe, or Salon. Categories and default service prices are created automatically.</p>
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
    <a href="{{ route('services.categories') }}" class="btn btn-outline-secondary ml-2">Go to Categories</a>
  </form>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Custom service business</h3>
  <p class="text-muted small mb-2">Define your own business name and categories. Add multiple services under the same category (one per line):<br>
    <code>Printing | A4 Black &amp; White | per page | 100</code><br>
    <code>Printing | A4 Color | per page | 300</code></p>
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
@else
<div class="alert alert-warning">You do not have permission to register service businesses.</div>
@endcan
@endsection

@section('scripts')
@include('services.partials.register-scripts')
@endsection
