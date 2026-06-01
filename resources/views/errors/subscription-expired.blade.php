@extends('layouts.app')

@section('title', 'Subscription Expired - SP-POS')

@section('content')
@php
    $supportEmail = platform_settings('support_email', 'admin@sp-pos.com');
    $supportPhone = platform_settings('support_phone');
@endphp
<div class="row justify-content-center" style="margin-top: 60px;">
  <div class="col-md-9">
    <div class="tile">
      <div class="tile-title text-danger text-center">
        <h1><i class="fa fa-lock fa-3x"></i></h1>
        <h2 class="mt-3">Service Suspended</h2>
      </div>
      <div class="tile-body">
        <p class="lead text-center">Your business subscription for <strong>{{ $business->name }}</strong> has expired or is inactive.</p>
        <p class="text-center text-muted">Pay the amount below to renew and restore access to your POS data.</p>

        @include('partials.subscription-billing', ['overview' => $overview, 'business' => $business])

        <div class="alert alert-warning text-left">
          <i class="fa fa-info-circle"></i> <strong>Support Contact</strong><br>
          Email: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
          @if($supportPhone)
            <br>Phone: {{ $supportPhone }}
          @endif
        </div>
      </div>
      <div class="tile-footer text-center">
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-primary">
          <i class="fa fa-sign-out"></i> Log Out
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
