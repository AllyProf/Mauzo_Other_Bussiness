@extends('layouts.app')

@section('title', 'Edit Item - SpareParts POS')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Item: {{ $item->name }}</h1>
    <p>Update item details</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('items.index') }}">Items</a></li>
    <li class="breadcrumb-item"><a href="#">Edit</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Item Details</h3>
      <div class="tile-body">
        <form action="{{ route('items.update', $item->id) }}" method="POST">
          @csrf
          @method('PUT')

          @include('items.partials.business-type-selector')

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Item Name</label>
                <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $item->name) }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="form-group">
                <label class="control-label">Category</label>
                <select class="form-control" name="category_id" id="itemCategorySelect">
                    <option value="">Select Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) old('category_id', $item->category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @if($multiBusiness ?? false)
                  <small class="text-muted">Only categories for the selected business type are shown.</small>
                @endif
              </div>
              <div class="form-group">
                <label class="control-label">Brand / Manufacturer</label>
                <input class="form-control" type="text" name="brand" value="{{ old('brand', $item->brand) }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="tile bg-light p-3">
                <h5 class="mb-3"><i class="fa fa-archive"></i> Unit & Packaging Setup</h5>
                
                @include('items.partials.receiving-package-field', ['item' => $item])

                @include('items.partials.selling-packages-field', ['item' => $item])

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group mb-0">
                      <label class="control-label">Buying Price (TZS)</label>
                      <input class="form-control @error('cost_price') is-invalid @enderror" type="number" name="cost_price" value="{{ old('cost_price', $primaryPackaging->cost_price ?? 0) }}" min="0" step="1">
                      @error('cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group mb-0">
                      <label class="control-label">Selling Price (TZS)</label>
                      <input class="form-control @error('selling_price') is-invalid @enderror" type="number" name="selling_price" value="{{ old('selling_price', $primaryPackaging->selling_price ?? 0) }}" min="0" step="1">
                      @error('selling_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                  </div>
                </div>
                <small class="text-muted d-block mt-2">
                  Update prices here when you need to raise or lower them without receiving new stock. POS uses the selling price immediately.
                </small>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">Description</label>
            <textarea class="form-control" name="description" rows="3">{{ old('description', $item->description) }}</textarea>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-fw fa-lg fa-check-circle"></i>Update Item</button>
            &nbsp;&nbsp;&nbsp;
            <a class="btn btn-secondary" href="{{ route('items.index') }}"><i class="fa fa-fw fa-lg fa-times-circle"></i>Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
