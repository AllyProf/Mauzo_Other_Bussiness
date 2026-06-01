@extends('layouts.app')

@section('title', 'Register Supplier')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Register Supplier</h1>
    <p>Add a new supplier to your business network</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('suppliers.store') }}" method="POST">
          @csrf
          <div class="form-group">
            <label class="control-label">Supplier Name</label>
            <input class="form-control" type="text" name="name" placeholder="e.g. Arusha Auto Parts" required>
          </div>
          <div class="form-group">
            <label class="control-label">Phone Number</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
              <input class="form-control" type="text" name="phone" placeholder="700 000 000" maxlength="9" required>
            </div>
            <small class="text-muted">Enter the last 9 digits of the phone number.</small>
          </div>
          <div class="form-group">
            <label class="control-label">Email Address</label>
            <input class="form-control" type="email" name="email" placeholder="e.g. info@supplier.com">
          </div>
          <div class="form-group">
            <label class="control-label">Region</label>
            <select class="form-control" name="region">
                <option value="">-- Select Region --</option>
                <option value="Arusha">Arusha</option>
                <option value="Dar es Salaam">Dar es Salaam</option>
                <option value="Dodoma">Dodoma</option>
                <option value="Mbeya">Mbeya</option>
                <option value="Mwanza">Mwanza</option>
                <option value="Morogoro">Morogoro</option>
                <option value="Tanga">Tanga</option>
                <option value="Kilimanjaro">Kilimanjaro</option>
                <option value="Zanzibar">Zanzibar</option>
            </select>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Register Supplier</button>
            <a class="btn btn-secondary" href="{{ route('suppliers.index') }}"><i class="fa fa-times-circle"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
