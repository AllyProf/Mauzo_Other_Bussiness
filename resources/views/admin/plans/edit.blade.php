@extends('layouts.app')

@section('title', 'Edit Plan - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Plan</h1>
    <p>Update subscription tier details</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Subscriptions</a></li>
    <li class="breadcrumb-item active">{{ $plan->name }}</li>
  </ul>
</div>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="tile">
      <h3 class="tile-title">Plan: {{ $plan->name }}</h3>
      <div class="tile-body">
        @include('admin.plans.partials.plan-form', [
          'plan' => $plan,
          'formAction' => route('admin.plans.update', $plan->id),
          'formMethod' => 'PUT',
          'submitLabel' => 'Update Plan',
        ])
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
@include('admin.plans.partials.plan-form-scripts')
@endsection
