<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <title>{{ __('auth.register_title') }} — {{ $platformSettings['platform_name'] ?? config('app.name') }}</title>
  <style>
    body, .login-content .logo h1, .login-box .login-head, .wizard-step-title {
      font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
    }
    body.register-auth-page { overflow-x: hidden; }
    body.register-auth-page .material-half-bg .cover {
      position: fixed;
      inset: 0;
      background-image: linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.45)),
        url('{{ asset('panel-assets/img/mauzo-pos-bg.png') }}');
      background-size: cover;
      background-position: center;
    }
    body.register-auth-page .login-content {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      justify-content: flex-start;
      padding: 70px 12px 40px;
    }
    .login-content .logo { margin-bottom: 20px; }
    .login-content .logo h1 {
      font-weight: 900;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #fff;
      font-size: clamp(1.6rem, 5vw, 2.6rem);
    }
    .login-language-switcher {
      position: fixed;
      top: 12px;
      right: 12px;
      z-index: 100;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      justify-content: flex-end;
      max-width: calc(100% - 24px);
    }
    .login-box.register-box {
      position: relative;
      width: 100%;
      max-width: 720px;
      min-height: auto !important;
      height: auto !important;
      padding: 0;
      margin: 0 auto 24px;
      perspective: none;
      overflow: hidden;
      border-radius: 6px;
    }
    .register-box-header {
      padding: 24px 24px 0;
    }
    .login-box.register-box .login-head {
      color: #940000 !important;
      margin-bottom: 6px;
      padding-bottom: 14px;
      font-size: clamp(1rem, 3vw, 1.15rem);
    }
    .register-subtitle {
      text-align: center;
      color: #6c757d;
      font-size: 13px;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    /* Wizard progress */
    .wizard-progress {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 24px;
      gap: 4px;
    }
    .wizard-progress-item {
      flex: 1;
      text-align: center;
      position: relative;
      min-width: 0;
    }
    .wizard-progress-item:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 15px;
      left: calc(50% + 18px);
      right: calc(-50% + 18px);
      height: 2px;
      background: #dee2e6;
      z-index: 0;
    }
    .wizard-progress-item.done:not(:last-child)::after {
      background: #940000;
    }
    .wizard-progress-item.active:not(:last-child)::after {
      background: linear-gradient(90deg, #940000 0%, #dee2e6 100%);
    }
    .wizard-progress-dot {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #e9ecef;
      color: #6c757d;
      font-size: 13px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
      z-index: 1;
      margin: 0 auto 6px;
      transition: all .2s;
    }
    .wizard-progress-item.active .wizard-progress-dot {
      background: #940000;
      color: #fff;
      box-shadow: 0 0 0 4px rgba(148,0,0,.15);
    }
    .wizard-progress-item.done .wizard-progress-dot {
      background: #6b0000;
      color: #fff;
    }
    .wizard-progress-label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      color: #6c757d;
      line-height: 1.2;
      padding: 0 2px;
    }
    .wizard-progress-item.active .wizard-progress-label { color: #940000; }
    .wizard-progress-item.done .wizard-progress-label { color: #6b0000; }
    .required-mark { color: #dc3545; margin-left: 2px; font-weight: 700; }
    .email-suggest { position: relative; }
    .email-suggest__list {
      position: absolute; top: 100%; left: 0; right: 0; z-index: 20; margin: 2px 0 0; padding: 4px 0;
      list-style: none; background: #fff; border: 1px solid #ced4da; border-radius: 4px;
      box-shadow: 0 6px 16px rgba(0,0,0,.12); max-height: 220px; overflow-y: auto;
    }
    .email-suggest__list li { padding: 7px 12px; font-size: 13px; cursor: pointer; }
    .email-suggest__list li strong { color: #940000; }
    .email-suggest__list li.is-active, .email-suggest__list li:hover { background: #f5eaea; }

    .register-form {
      position: static !important;
      padding: 8px 24px 24px !important;
      transform: none !important;
      opacity: 1 !important;
    }
    .wizard-panel {
      display: none;
      animation: wizardFade .25s ease;
    }
    .wizard-panel.active { display: block; }
    @keyframes wizardFade {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .wizard-step-title {
      color: #940000;
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin: 0 0 16px;
      padding-bottom: 8px;
      border-bottom: 1px solid #eee;
    }
    .wizard-step-title i { margin-right: 6px; }
    .register-form .form-group { margin-bottom: 14px; }
    .phone-input-group { display: flex; align-items: stretch; }
    .phone-prefix {
      display: flex;
      align-items: center;
      padding: 0 12px;
      background: #f8f9fa;
      border: 1px solid #ced4da;
      border-right: 0;
      border-radius: 4px 0 0 4px;
      font-weight: 700;
      font-size: 13px;
    }
    .phone-input-group .form-control { border-radius: 0 4px 4px 0; }
    .field-help {
      display: block;
      font-size: 11px;
      color: #6c757d;
      margin-top: 4px;
    }
    .optional-tag { font-weight: 400; font-size: 11px; color: #6c757d; }
    .register-note {
      background: #fff8f8;
      border-left: 4px solid #940000;
      padding: 12px 14px;
      font-size: 12px;
      border-radius: 0 4px 4px 0;
      margin-top: 12px;
    }
    .verification-code-input {
      letter-spacing: .35em;
      font-size: clamp(1.2rem, 5vw, 1.5rem);
      text-align: center;
      font-weight: 700;
      max-width: 240px;
      margin: 0 auto;
    }
    .verify-icon-wrap {
      text-align: center;
      margin-bottom: 12px;
    }
    .verify-icon-wrap i {
      font-size: 42px;
      color: #940000;
      opacity: .85;
    }
    .wizard-nav {
      display: flex;
      gap: 10px;
      margin-top: 20px;
      flex-wrap: wrap;
    }
    .wizard-nav .btn { flex: 1; min-width: 120px; font-weight: 600; }
    .wizard-nav .btn-back {
      background: #f1f3f5;
      border-color: #dee2e6;
      color: #495057;
    }
    .wizard-nav .btn-back:hover { background: #e9ecef; color: #333; }
    .btn-primary {
      background-color: #940000 !important;
      border-color: #940000 !important;
    }
    .btn-primary:hover, .btn-primary:focus {
      background-color: #7a0000 !important;
      border-color: #7a0000 !important;
    }
    .resend-link { color: #940000; font-weight: 600; font-size: 13px; }
    .auth-back-links {
      text-align: center;
      padding: 16px 24px 22px;
      border-top: 1px solid #eee;
      font-size: 13px;
    }
    .auth-back-links a { color: #940000; font-weight: 600; }
    .register-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: rgba(0,0,0,.82);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .register-progress-card {
      width: 100%;
      max-width: 380px;
      background: #fff;
      border-radius: 8px;
      padding: 28px 20px;
      text-align: center;
    }
    .register-progress-card h4 { color: #940000; margin: 14px 0 8px; font-weight: 700; }
    .register-progress-card p { color: #6c757d; min-height: 40px; font-size: 14px; }
    .register-progress-runner {
      width: 100%; height: 170px; margin: 0 auto; border-radius: 8px; overflow: hidden;
      background: #3b3b3b;
    }
    .register-progress-runner img {
      width: 100%; height: 100%; object-fit: cover; display: block;
      transform: scaleX(-1);
    }
    .register-progress-percent {
      font-size: 26px; font-weight: 700; color: #940000; line-height: 1; margin: 4px 0 10px;
      font-variant-numeric: tabular-nums;
    }
    .register-progress-bar {
      height: 6px; background: #eee; border-radius: 999px; overflow: hidden;
    }
    .register-progress-bar span {
      display: block; height: 100%; width: 0; background: #940000;
      transition: width .25s linear;
    }
    @keyframes register-spin { to { transform: rotate(360deg); } }
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single {
      height: 38px; border-color: #ced4da;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
    }
    .field-error {
      font-size: 11px;
      color: #dc3545;
      margin-top: 4px;
      display: none;
    }
    .field-error.show { display: block; }
    .form-control.is-invalid { border-color: #dc3545; }
    
    .contact-header a {
      display: flex; align-items: center; justify-content: center;
      width: 44px; height: 44px; border-radius: 50%;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      transition: all 0.2s ease;
      text-decoration: none;
    }
    .contact-header a:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.4); }
    .contact-phone-btn { background: #fff; }
    .contact-phone-btn i { color: #940000; font-size: 20px; }
    .contact-wa-btn { background: #25D366; }
    .contact-wa-btn i { color: #fff; font-size: 24px; }

    @media (max-width: 575.98px) {
      body.register-auth-page .login-content { padding: 90px 10px 32px; }
      .contact-header { top: 12px !important; left: 12px !important; gap: 8px !important; }
      .contact-header a { width: 38px; height: 38px; }
      .contact-phone-btn i { font-size: 16px; }
      .contact-wa-btn i { font-size: 20px; }
      .register-box-header,
      .register-form,
      .auth-back-links { padding-left: 16px !important; padding-right: 16px !important; }
      .register-box-header { padding-top: 18px !important; }
      .wizard-progress-label { font-size: 10px; }
      .wizard-progress-dot { width: 28px; height: 28px; font-size: 12px; }
      .wizard-progress-item:not(:last-child)::after { top: 13px; }
      .wizard-nav { flex-direction: column-reverse; }
      .wizard-nav .btn { width: 100%; flex: none; }
      .login-language-switcher { top: 8px; right: 8px; }
      .login-language-switcher .btn { font-size: 11px; padding: 4px 8px; }
    }
    @media (max-width: 380px) {
      .wizard-progress-label { display: none; }
    }
  </style>
</head>
<body class="register-auth-page">
@php
  $trialDays = (int) ($platformSettings['default_trial_days'] ?? 30);
  $oldPhone = old('phone');
  if ($oldPhone) {
      $oldPhone = preg_replace('/^\+?255/', '', preg_replace('/\D/', '', $oldPhone));
  }
  $businessTypes = category_templates();
@endphp

@include('partials.language-switcher-login')

<section class="material-half-bg"><div class="cover"></div></section>

<section class="login-content register-page">
  <div class="contact-header" style="position: fixed; top: 20px; left: 20px; z-index: 100; display: flex; gap: 12px;">
    <a href="tel:0616775800" class="contact-phone-btn" title="Call Us"><i class="fa fa-phone"></i></a>
    <a href="https://wa.me/255616775800" target="_blank" class="contact-wa-btn" title="WhatsApp Us"><i class="fa fa-whatsapp"></i></a>
  </div>

  <div class="logo">
    <h1>{{ $platformSettings['platform_name'] ?? 'SpareParts' }}</h1>
  </div>

  <div class="login-box register-box">
    <div class="register-box-header">
      <h3 class="login-head"><i class="fa fa-building fa-lg fa-fw"></i> {{ strtoupper(__('auth.register_heading')) }}</h3>
      <p class="register-subtitle">
        {{ __('auth.register_subtitle', ['days' => $trialDays]) }}
      </p>

      <div class="wizard-progress" id="wizard-progress">
        <div class="wizard-progress-item active" data-step="1">
          <div class="wizard-progress-dot">1</div>
          <span class="wizard-progress-label">{{ __('auth.register_step_account') }}</span>
        </div>
        <div class="wizard-progress-item" data-step="2">
          <div class="wizard-progress-dot">2</div>
          <span class="wizard-progress-label">{{ __('auth.register_step_location') }}</span>
        </div>
        <div class="wizard-progress-item" data-step="3">
          <div class="wizard-progress-dot">3</div>
          <span class="wizard-progress-label">{{ __('auth.register_step_verify') }}</span>
        </div>
      </div>

      @if($errors->any())
        <div class="alert alert-danger mb-3">
          @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
      @endif
      <div id="register-alert" class="alert alert-danger d-none mb-3"></div>
    </div>

    <form id="register-form" class="register-form" action="{{ route('register.business.store') }}" method="POST" novalidate>
      @csrf

      {{-- Step 1: Account --}}
      <div class="wizard-panel active" data-panel="1">
        <div class="wizard-step-title"><i class="fa fa-user"></i> {{ __('auth.register_your_account') }}</div>
        
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">{{ strtoupper(__('auth.register_full_name')) }}<span class="required-mark">*</span></label>
              <input class="form-control" type="text" name="name" id="name" value="{{ old('name') }}" placeholder="{{ __('auth.register_full_name_placeholder') }}" required autofocus>
              <div class="field-error" data-for="name">{{ __('auth.register_error_name') }}</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">{{ strtoupper(__('auth.register_phone')) }}<span class="required-mark">*</span></label>
              @include('partials.phone-country-input', ['id' => 'phone', 'required' => true, 'countryValue' => old('phone_country'), 'localValue' => $oldPhone ?? '', 'placeholder' => '712345678'])
              <small class="field-help">{{ __('auth.register_phone_help') }}</small>
              <div class="field-error" data-for="phone">{{ __('auth.register_error_phone') }}</div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">{{ strtoupper(__('auth.register_email_optional')) }}</label>
              <div class="email-suggest">
                <input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="{{ __('auth.register_email_placeholder') }}" autocomplete="off" aria-autocomplete="list">
                <ul class="email-suggest__list d-none" id="emailSuggestList" role="listbox"></ul>
              </div>
              <small class="field-help">{{ __('auth.register_email_help') }}</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group mb-0">
              <label class="control-label">{{ strtoupper(__('auth.register_business_type')) }}<span class="required-mark">*</span></label>
              <select class="form-control" name="business_type" id="business_type" required>
                <option value="">{{ __('auth.register_select_business_type') }}</option>
                @foreach($businessTypes as $key => $type)
                <option value="{{ $key }}" {{ old('business_type') === $key ? 'selected' : '' }}>{{ $type['label'] ?? $key }}</option>
                @endforeach
                <option value="other" {{ old('business_type') === 'other' ? 'selected' : '' }}>Other</option>
              </select>
              <div class="field-error" data-for="business_type">{{ __('auth.register_error_business_type') }}</div>
            </div>
            <div class="form-group mt-3 {{ old('business_type') === 'other' ? '' : 'd-none' }}" id="custom_business_type_group">
              <label class="control-label">BUSINESS NAME<span class="required-mark">*</span></label>
              <input class="form-control" type="text" name="custom_business_type" id="custom_business_type" value="{{ old('custom_business_type') }}" placeholder="Enter your business name">
              <div class="field-error" data-for="custom_business_type">Please enter your business name</div>
            </div>
          </div>
        </div>

        <div class="wizard-nav mt-3">
          <button type="button" class="btn btn-primary" id="btn-next-1">{{ __('auth.register_next') }} <i class="fa fa-arrow-right"></i></button>
        </div>
      </div>

      {{-- Step 2: Location --}}
      <div class="wizard-panel" data-panel="2">
        <div class="wizard-step-title"><i class="fa fa-map-marker"></i> {{ __('auth.register_business_location') }}</div>
        
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">{{ strtoupper(__('auth.register_region')) }}<span class="required-mark">*</span></label>
              <select class="form-control" name="region" id="businessRegion" required>
                <option value="">{{ __('auth.register_select_region') }}</option>
                @foreach(tanzania_regions() as $region)
                <option value="{{ $region }}" {{ old('region') === $region ? 'selected' : '' }}>{{ $region }}</option>
                @endforeach
              </select>
              <div class="field-error" data-for="region">{{ __('auth.register_error_region') }}</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">{{ strtoupper(__('auth.register_district')) }}<span class="required-mark">*</span></label>
              <select class="form-control" name="district" id="businessDistrict" required>
                <option value="">{{ old('region') ? __('auth.register_select_district') : __('auth.register_select_region_first') }}</option>
                @foreach(old('region') ? tanzania_districts(old('region')) : [] as $district)
                <option value="{{ $district }}" {{ old('district') === $district ? 'selected' : '' }}>{{ $district }}</option>
                @endforeach
              </select>
              <div class="field-error" data-for="district">{{ __('auth.register_error_district') }}</div>
            </div>
          </div>
        </div>

        <div class="form-group mb-0">
          <label class="control-label">{{ strtoupper(__('auth.register_physical_address')) }}<span class="required-mark">*</span></label>
          <textarea class="form-control" name="address" id="address" rows="2" placeholder="{{ __('auth.register_address_placeholder') }}" required>{{ old('address') }}</textarea>
          <div class="field-error" data-for="address">{{ __('auth.register_error_address') }}</div>
        </div>
        
        <div class="wizard-nav mt-3">
          <button type="button" class="btn btn-back" id="btn-back-2"><i class="fa fa-arrow-left"></i> {{ __('auth.register_back') }}</button>
          <button type="button" class="btn btn-primary" id="btn-next-2">{{ __('auth.register_next') }} <i class="fa fa-arrow-right"></i></button>
        </div>
      </div>

      {{-- Step 3: Verify --}}
      <div class="wizard-panel" data-panel="3">
        <div class="wizard-step-title text-center"><i class="fa fa-mobile"></i> {{ __('auth.register_verify_phone') }}</div>
        <div class="verify-icon-wrap"><i class="fa fa-commenting-o"></i></div>
        <p class="text-center field-help mb-3">
          {{ __('auth.register_verify_prompt') }} <strong id="sent-phone-display">+255...</strong>
        </p>
        <div class="form-group text-center mb-2">
          <input class="form-control verification-code-input" type="text" name="verification_code" id="verification_code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
          <div class="field-error text-center" data-for="verification_code">{{ __('auth.register_error_code') }}</div>
        </div>
        <p class="text-center mb-0">
          <button type="button" id="resend-code-btn" class="btn btn-link resend-link p-0">{{ __('auth.register_resend_code') }}</button>
        </p>
        <div class="register-note">
          <i class="fa fa-info-circle"></i>
          {{ __('auth.register_note') }}
        </div>
        <div class="wizard-nav">
          <button type="button" class="btn btn-back" id="btn-back-3"><i class="fa fa-arrow-left"></i> {{ __('auth.register_back') }}</button>
          <button type="submit" class="btn btn-primary" id="register-submit-btn">
            <i class="fa fa-check-circle"></i> <span id="register-submit-text">{{ strtoupper(__('auth.register_submit')) }}</span>
          </button>
        </div>
      </div>
    </form>

    <div class="auth-back-links">
      {{ __('auth.register_already') }} <a href="{{ route('login') }}">{{ __('auth.register_sign_in') }}</a>
      &nbsp;·&nbsp;
      <a href="{{ route('landing.index') }}">{{ __('auth.register_back_home') }}</a>
    </div>
  </div>
</section>

<div id="register-progress-overlay" class="register-progress-overlay d-none" aria-hidden="true">
  <div class="register-progress-card">
    <div class="register-progress-runner">
      <img src="{{ asset('gp-assets/img/boy_running.gif') }}" alt="" aria-hidden="true">
    </div>
    <h4 id="register-progress-title">{{ __('auth.register_please_wait') }}</h4>
    <p id="register-progress-message">{{ __('auth.register_processing') }}</p>
    <div class="register-progress-percent" id="register-progress-percent">0%</div>
    <div class="register-progress-bar"><span id="register-progress-fill"></span></div>
  </div>
</div>

<script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
<script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
<script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('panel-assets/js/main.js') }}"></script>
@include('partials.tanzania-location-select2', [
  'selectedDistrict' => old('district', ''),
  'disableDistrictWhenEmpty' => true,
  'selectRegionFirst' => __('auth.register_select_region_first'),
  'selectDistrict' => __('auth.register_select_district'),
  'selectRegion' => __('auth.register_select_region'),
])
@php
  $registerI18n = [
    'genericError' => __('auth.register_generic_error'),
    'networkError' => __('auth.register_network_error'),
    'sendingTitle' => __('auth.register_sending_title'),
    'submittingTitle' => __('auth.register_submitting_title'),
    'received' => __('auth.register_received'),
    'pendingDefault' => __('auth.register_pending_default'),
    'resendCode' => __('auth.register_resend_code'),
    'resendIn' => __('auth.register_resend_in'),
    'sendingMessages' => [
      __('auth.register_sending_1'),
      __('auth.register_sending_2'),
      __('auth.register_sending_3'),
    ],
    'creatingMessages' => [
      __('auth.register_submitting_1'),
      __('auth.register_submitting_2'),
      __('auth.register_submitting_3'),
    ],
  ];
@endphp
<script>
(function () {
  var form = document.getElementById('register-form');
  var currentStep = 1;
  var codeSent = false;
  var isSubmitting = false;
  var messageTimer = null;
  var resendCooldown = null;

  var overlay = document.getElementById('register-progress-overlay');
  var progressTitle = document.getElementById('register-progress-title');
  var progressMessage = document.getElementById('register-progress-message');
  var progressFill = document.getElementById('register-progress-fill');
  var alertBox = document.getElementById('register-alert');
  var verificationInput = document.getElementById('verification_code');
  var sentPhoneDisplay = document.getElementById('sent-phone-display');
  var resendBtn = document.getElementById('resend-code-btn');
  var submitBtn = document.getElementById('register-submit-btn');

  var progressItems = document.querySelectorAll('.wizard-progress-item');
  var panels = document.querySelectorAll('.wizard-panel');

  var i18n = @json($registerI18n);

  var sendingMessages = i18n.sendingMessages;
  var creatingMessages = i18n.creatingMessages;

  function csrfToken() {
    return document.querySelector('input[name="_token"]').value;
  }

  function formData() {
    var data = new FormData(form);
    data.set('phone', (data.get('phone') || '').toString().replace(/\D/g, ''));
    return data;
  }

  function showAlert(msg) {
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideAlert() {
    alertBox.classList.add('d-none');
    alertBox.textContent = '';
  }

  var progressPercent = document.getElementById('register-progress-percent');
  var progressRunner = document.querySelector('.register-progress-runner');
  var percentTimer = null;
  var percentValue = 0;

  function setPercent(value) {
    percentValue = Math.max(0, Math.min(100, value));
    var rounded = Math.floor(percentValue);
    progressFill.style.width = rounded + '%';
    progressPercent.textContent = rounded + '%';
  }

  function showOverlay(title, messages) {
    overlay.classList.remove('d-none');
    progressRunner.style.display = '';
    progressPercent.style.display = '';
    progressTitle.textContent = title;
    setPercent(0);
    var i = 0;
    progressMessage.textContent = messages[0];
    clearInterval(messageTimer);
    clearInterval(percentTimer);
    messageTimer = setInterval(function () {
      i = (i + 1) % messages.length;
      progressMessage.textContent = messages[i];
    }, 1600);
    percentTimer = setInterval(function () {
      setPercent(Math.min(95, percentValue + Math.max(0.3, (95 - percentValue) * 0.06)));
    }, 120);
  }

  function finishProgress() {
    clearInterval(percentTimer);
    setPercent(100);
  }

  function hideOverlay() {
    overlay.classList.add('d-none');
    clearInterval(messageTimer);
    clearInterval(percentTimer);
    setPercent(0);
  }

  function parseErrors(payload) {
    if (payload && payload.errors) return Object.values(payload.errors).flat().join(' ');
    return (payload && payload.message) ? payload.message : i18n.genericError;
  }

  function clearFieldErrors() {
    document.querySelectorAll('.field-error').forEach(function (el) { el.classList.remove('show'); });
    document.querySelectorAll('.form-control.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
  }

  function fieldError(name, show) {
    var input = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
    var err = document.querySelector('.field-error[data-for="' + name + '"]');
    if (show) {
      if (input) input.classList.add('is-invalid');
      if (err) err.classList.add('show');
    }
  }

  function validateStep(step) {
    clearFieldErrors();
    hideAlert();
    var ok = true;

    if (step === 1) {
      var name = document.getElementById('name');
      var phone = document.getElementById('phone');
      var btype = document.getElementById('business_type');
      if (!name.value.trim()) { fieldError('name', true); ok = false; }
      var ph = phone.value.replace(/\D/g, '').replace(/^0+/, '');
      var phoneWrap = phone.closest('[data-phone-country]');
      var phoneIso = phoneWrap ? phoneWrap.querySelector('.phone-country-value').value : 'tz';
      var phoneOk = phoneIso === 'tz' ? /^[678]\d{8}$/.test(ph) : (ph.length >= 4 && ph.length <= 14);
      if (!phoneOk) { fieldError('phone', true); ok = false; }
      if (!btype.value) { fieldError('business_type', true); ok = false; }
      if (btype.value === 'other') {
        var customBType = document.getElementById('custom_business_type');
        if (!customBType.value.trim()) { fieldError('custom_business_type', true); ok = false; }
      }
    }

    if (step === 2) {
      var region = document.getElementById('businessRegion');
      var district = document.getElementById('businessDistrict');
      var address = document.getElementById('address');
      if (!region.value) { fieldError('region', true); ok = false; }
      if (!district.value) { fieldError('district', true); ok = false; }
      if (!address.value.trim()) { fieldError('address', true); ok = false; }
    }

    if (step === 3) {
      if (!verificationInput.value || verificationInput.value.trim().length !== 6) {
        fieldError('verification_code', true);
        ok = false;
      }
    }

    return ok;
  }

  function goToStep(step) {
    currentStep = step;
    panels.forEach(function (p) {
      p.classList.toggle('active', parseInt(p.getAttribute('data-panel'), 10) === step);
    });
    progressItems.forEach(function (item) {
      var n = parseInt(item.getAttribute('data-step'), 10);
      item.classList.remove('active', 'done');
      if (n < step) item.classList.add('done');
      if (n === step) item.classList.add('active');
    });
    hideAlert();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    var panel = document.querySelector('.wizard-panel[data-panel="' + step + '"]');
    if (panel) {
      var focusEl = panel.querySelector('input:not([type=hidden]), select, textarea');
      if (focusEl && step !== 3) setTimeout(function () { focusEl.focus(); }, 200);
    }
  }

  function sendVerificationCode() {
    if (!validateStep(1) || !validateStep(2)) {
      if (!validateStep(1)) goToStep(1);
      else goToStep(2);
      return Promise.resolve(false);
    }

    hideAlert();
    showOverlay(i18n.sendingTitle, sendingMessages);

    return fetch(@json(route('register.business.send-code')), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
      body: formData()
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (result) {
      if (!result.ok) {
        hideOverlay();
        showAlert(parseErrors(result.data));
        return false;
      }
      finishProgress();
      codeSent = true;
      sentPhoneDisplay.textContent = result.data.phone_display || '+255';
      return new Promise(function (resolve) {
        setTimeout(function () {
          hideOverlay();
          goToStep(3);
          verificationInput.focus();
          resolve(true);
        }, 450);
      });
    })
    .catch(function () {
      hideOverlay();
      showAlert(i18n.networkError);
      return false;
    });
  }

  function completeRegistration() {
    if (!validateStep(3) || isSubmitting) return;
    isSubmitting = true;
    hideAlert();
    showOverlay(i18n.submittingTitle, creatingMessages);
    submitBtn.disabled = true;

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
      body: formData()
    })
    .then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; })
        .catch(function () { return { ok: r.ok, data: {} }; });
    })
    .then(function (result) {
      if (!result.ok) {
        hideOverlay();
        isSubmitting = false;
        submitBtn.disabled = false;
        showAlert(parseErrors(result.data));
        return;
      }
      finishProgress();
      progressRunner.style.display = 'none';
      progressPercent.style.display = 'none';
      progressTitle.innerHTML = '<div style="animation: wizardFade 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);"><i class="fa fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 12px; display: block; text-shadow: 0 4px 12px rgba(40,167,69,0.3);"></i></div>' + i18n.received;
      progressMessage.textContent = result.data.message || i18n.pendingDefault;
      clearInterval(messageTimer);
      setTimeout(function () {
        window.location.href = result.data.redirect || @json(route('landing.index'));
      }, 2200);
    })
    .catch(function () {
      hideOverlay();
      isSubmitting = false;
      submitBtn.disabled = false;
      showAlert(i18n.networkError);
    });
  }

  document.getElementById('business_type').addEventListener('change', function (e) {
    var customGroup = document.getElementById('custom_business_type_group');
    if (e.target.value === 'other') {
      customGroup.classList.remove('d-none');
      var input = document.getElementById('custom_business_type');
      if (input) input.focus();
    } else {
      customGroup.classList.add('d-none');
    }
  });

  document.getElementById('btn-next-1').addEventListener('click', function () {
    if (validateStep(1)) goToStep(2);
  });

  document.getElementById('btn-back-2').addEventListener('click', function () {
    goToStep(1);
  });

  document.getElementById('btn-next-2').addEventListener('click', function () {
    if (!validateStep(2)) return;
    if (codeSent) {
      goToStep(3);
      return;
    }
    sendVerificationCode();
  });

  document.getElementById('btn-back-3').addEventListener('click', function () {
    goToStep(2);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (currentStep !== 3) return;
    if (!codeSent) {
      sendVerificationCode();
      return;
    }
    completeRegistration();
  });

  resendBtn.addEventListener('click', function () {
    if (resendCooldown) return;
    verificationInput.value = '';
    sendVerificationCode().then(function (sent) {
      if (!sent) return;
      resendBtn.disabled = true;
      var seconds = 60;
      resendBtn.textContent = i18n.resendIn.replace(':seconds', seconds);
      resendCooldown = setInterval(function () {
        seconds -= 1;
        if (seconds <= 0) {
          clearInterval(resendCooldown);
          resendCooldown = null;
          resendBtn.disabled = false;
          resendBtn.textContent = i18n.resendCode;
          return;
        }
        resendBtn.textContent = i18n.resendIn.replace(':seconds', seconds);
      }, 1000);
    });
  });

  verificationInput.addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    if (e.target.value.length === 6 && currentStep === 3 && codeSent && !isSubmitting) {
      completeRegistration();
    }
  });

  document.getElementById('phone').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 14);
  });

  goToStep(1);
})();

(function () {
  var input = document.getElementById('email');
  var list = document.getElementById('emailSuggestList');
  if (!input || !list) return;

  var DOMAINS = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'live.com', 'ymail.com', 'protonmail.com'];
  var active = -1;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function hide() { list.classList.add('d-none'); list.innerHTML = ''; active = -1; }

  function pick(value) {
    input.value = value;
    hide();
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function show() {
    var value = input.value.trim();
    var at = value.indexOf('@');
    if (!value || /\s/.test(value) || (at === -1 && value.length < 2) || value.indexOf('@') !== value.lastIndexOf('@')) { hide(); return; }

    var user = at === -1 ? value : value.slice(0, at);
    var typed = at === -1 ? '' : value.slice(at + 1).toLowerCase();
    if (!user) { hide(); return; }

    var matches = DOMAINS.filter(function (d) { return d.indexOf(typed) === 0 && d !== typed; }).slice(0, 6);
    if (!matches.length) { hide(); return; }

    list.innerHTML = matches.map(function (d) {
      var full = user + '@' + d;
      return '<li role="option" data-value="' + esc(full) + '">' + esc(user) + '@<strong>' + esc(d) + '</strong></li>';
    }).join('');
    list.classList.remove('d-none');
    active = -1;
  }

  input.addEventListener('input', show);
  input.addEventListener('focus', show);
  input.addEventListener('blur', function () { setTimeout(hide, 150); });

  input.addEventListener('keydown', function (e) {
    var items = list.querySelectorAll('li');
    if (list.classList.contains('d-none') || !items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = e.key === 'ArrowDown' ? (active + 1) % items.length : (active - 1 + items.length) % items.length;
      items.forEach(function (li, i) { li.classList.toggle('is-active', i === active); });
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (active >= 0 || (e.key === 'Tab' && input.value.indexOf('@') !== -1)) {
        e.preventDefault();
        pick(items[active >= 0 ? active : 0].getAttribute('data-value'));
      }
    } else if (e.key === 'Escape') {
      hide();
    }
  });

  list.addEventListener('mousedown', function (e) {
    var li = e.target.closest('li');
    if (li) { e.preventDefault(); pick(li.getAttribute('data-value')); }
  });
})();
</script>
</body>
</html>
