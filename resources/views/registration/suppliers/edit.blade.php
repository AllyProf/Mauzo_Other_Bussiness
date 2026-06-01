@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Supplier</h1>
    <p>Update contact information for {{ $supplier->name }}</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST">
          @csrf @method('PUT')
          <div class="form-group">
            <label class="control-label">Supplier Name</label>
            <input class="form-control" type="text" name="name" value="{{ $supplier->name }}" required>
          </div>
          <div class="form-group">
            <label class="control-label">Phone Number</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
              <input class="form-control" type="text" name="phone" value="{{ str_replace('+255', '', $supplier->phone) }}" maxlength="9" required>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">Email Address</label>
            <input class="form-control" type="email" name="email" value="{{ $supplier->email }}">
          </div>
          <div class="form-group">
            <label class="control-label">Region</label>
            <select class="form-control" name="region">
                <option value="">-- Select Region --</option>
                @foreach(['Arusha', 'Dar es Salaam', 'Dodoma', 'Mbeya', 'Mwanza', 'Morogoro', 'Tanga', 'Kilimanjaro', 'Zanzibar'] as $reg)
                    <option value="{{ $reg }}" {{ $supplier->region == $reg ? 'selected' : '' }}>{{ $reg }}</option>
                @endforeach
            </select>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Update Supplier</button>
            <a class="btn btn-secondary" href="{{ route('suppliers.index') }}"><i class="fa fa-times-circle"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
