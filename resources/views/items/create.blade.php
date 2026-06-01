@extends('layouts.app')

@section('title', 'Register Item - SpareParts POS')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Register New Item</h1>
    <p>Add a new spare part to your inventory</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Items</li>
    <li class="breadcrumb-item"><a href="#">Register</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Item Details</h3>
      <div class="tile-body">
        <form action="{{ route('items.store') }}" method="POST">
          @csrf
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Item Name</label>
                <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" placeholder="e.g. Brake Pads" value="{{ old('name') }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="form-group">
                <label class="control-label">Category</label>
                <select class="form-control" name="category_id">
                    <option value="">Select Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="control-label">Brand / Manufacturer</label>
                <input class="form-control" type="text" name="brand" placeholder="e.g. Toyota, Bosch, Samsung" value="{{ old('brand') }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="tile bg-light p-3">
                <h5 class="mb-3"><i class="fa fa-archive"></i> Unit & Packaging Setup</h5>
                
                <div class="form-group">
                  <label class="control-label">Receiving Package (Purchased as)</label>
                  <select class="form-control" name="receiving_packaging_id" required>
                      <option value="">Select Unit</option>
                      @foreach($packagingTypes as $pkg)
                          <option value="{{ $pkg->id }}">{{ $pkg->name }}</option>
                      @endforeach
                  </select>
                  <small class="text-muted">Unit used when buying from supplier (e.g. Box, Carton)</small>
                </div>

                @include('items.partials.selling-packages-field')
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="Optional details..."></textarea>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-fw fa-lg fa-check-circle"></i>Register Item</button>
            &nbsp;&nbsp;&nbsp;
            <a class="btn btn-secondary" href="{{ route('items.index') }}"><i class="fa fa-fw fa-lg fa-times-circle"></i>Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
