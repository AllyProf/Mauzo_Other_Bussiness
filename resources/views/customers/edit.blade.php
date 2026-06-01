@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Customer</h1>
    <p>Update details for {{ $customer->name }}</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('customers.update', $customer) }}" method="POST">
          @csrf @method('PUT')
          @include('customers.partials.form', ['customer' => $customer])
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Update Customer</button>
            <a class="btn btn-secondary" href="{{ route('customers.show', $customer) }}"><i class="fa fa-times-circle"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
