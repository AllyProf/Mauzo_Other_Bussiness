@extends('layouts.app')

@section('title', 'Create Plan - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Create New Plan</h1>
    <p>Define a new subscription tier for your users</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Subscriptions</a></li>
    <li class="breadcrumb-item active">Create Plan</li>
  </ul>
</div>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="tile">
      <h3 class="tile-title">Plan Details</h3>
      <div class="tile-body">
        @include('admin.plans.partials.plan-form', [
          'plan' => null,
          'formAction' => route('admin.plans.store'),
          'formMethod' => 'POST',
          'submitLabel' => 'Create Plan',
        ])
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
@include('admin.plans.partials.plan-form-scripts')
@endsection
