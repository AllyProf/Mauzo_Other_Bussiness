@extends('layouts.app')

@section('title', 'Register Customer')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Register Customer</h1>
    <p>Add a new customer to your business</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('customers.store') }}" method="POST">
          @csrf
          @include('customers.partials.form')
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Register Customer</button>
            <a class="btn btn-secondary" href="{{ route('customers.index') }}"><i class="fa fa-times-circle"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
