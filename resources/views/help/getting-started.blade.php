@extends('layouts.app')

@section('title', __('help.getting_started.title'))

@section('content')
@php($gs = __('help.getting_started'))

<div class="app-title">
  <div>
    <h1><i class="fa fa-book"></i> {{ $gs['title'] }}</h1>
    <p>{{ $gs['subtitle'] }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    @include('layouts.partials._breadcrumb_manual')
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('help.index') }}">{{ __('help.center') }}</a></li>
    <li class="breadcrumb-item active">{{ __('menu.system_guide') }}</li>
  </ul>
</div>

<h2 class="h5 text-muted mb-3 font-weight-bold">{{ $gs['recommended_order'] }}</h2>

@foreach($steps as $step)
  <div class="tile usage-guide-step p-0 mb-4 overflow-hidden" id="guide-step-{{ $step['number'] }}">
    <div class="usage-guide-step__head d-flex flex-wrap align-items-center justify-content-between px-4 py-3">
      <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
        <span class="usage-guide-step__num">{{ $gs['step'] }} {{ $step['number'] }}</span>
        <h3 class="h5 mb-0 font-weight-bold">{{ $step['title'] }}</h3>
        @if(!empty($step['optional']))
          <span class="badge badge-secondary">{{ $gs['optional'] }}</span>
        @endif
      </div>
      @if($step['can_open'])
        <a href="{{ $step['url'] }}" class="btn btn-sm btn-primary mt-2 mt-md-0">
          <i class="fa fa-external-link"></i> {{ $gs['open_screen'] }}
        </a>
      @endif
    </div>
    <div class="px-4 pb-3 pt-2">
      <p class="text-muted small mb-2"><i class="fa fa-map-signs"></i> <strong>{{ $gs['menu_path'] }}:</strong> {{ $step['menu'] }}</p>
      @if($step['retail_skip'])
        <p class="alert alert-secondary py-2 small mb-3">{{ $gs['retail_off'] }}</p>
      @elseif(!$step['can_open'] && !empty($step['route']))
        <p class="alert alert-light border py-2 small mb-3">{{ $gs['no_access'] }}</p>
      @endif
      @if(!empty($step['diagram']))
        @include('help.partials.menu-path-diagram', [
          'diagram' => $step['diagram'],
          'stepTitle' => $step['title'],
          'openUrl' => $step['can_open'] ? $step['url'] : null,
        ])
      @endif
      <p class="font-weight-bold mb-2 mt-3">{{ $gs['then_on_that_page'] }}</p>
      <ul class="mb-0 pl-3 usage-guide-step__bullets">
        @foreach($step['bullets'] as $bullet)
          <li class="mb-2">{{ $bullet }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endforeach

<div class="tile p-4">
  <p class="mb-2"><strong>{{ $gs['also_see'] }}</strong></p>
  <a href="{{ route('help.index') }}" class="btn btn-outline-secondary btn-sm mr-2 mb-2"><i class="fa fa-question-circle"></i> {{ $gs['help_articles'] }}</a>
  @if(Route::has('tickets.create'))
    <a href="{{ route('tickets.create') }}" class="btn btn-outline-primary btn-sm mb-2"><i class="fa fa-life-ring"></i> {{ $gs['support'] }}</a>
  @endif
</div>
@endsection

@push('styles')
<style>
  .usage-guide-step { border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .usage-guide-step__head { background: #f8f9fa; border-bottom: 1px solid #eee; }
  .usage-guide-step__num {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 2.5rem; height: 2.5rem; border-radius: 50%;
    background: #940000; color: #fff; font-weight: 700; font-size: 0.85rem;
  }
  .usage-guide-step__bullets { line-height: 1.55; }
  .usage-menu-diagram { margin-bottom: 0.5rem; }
  .usage-menu-diagram__caption {
    margin: 0 0 0.5rem; font-size: 0.875rem; color: #6c757d; font-weight: 400;
  }
  .usage-menu-diagram__frame {
    position: relative; display: flex; align-items: stretch; min-height: 220px;
    border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;
    background: #f4f5f7;
  }
  .usage-menu-diagram__sidebar {
    flex: 0 0 42%; max-width: 280px; min-width: 200px;
    padding: 12px 10px 14px; font-size: 12px; color: #fff;
    background: #222d32;
  }
  .usage-menu-diagram__menu-icon {
    flex: 0 0 22px; width: 22px; text-align: center; font-size: 13px; opacity: 0.95;
  }
  .usage-menu-diagram__row {
    display: flex; align-items: center; gap: 6px; padding: 8px 10px;
    line-height: 1.3; position: relative; border-radius: 0;
  }
  .usage-menu-diagram__row--top { font-size: 12px; color: #fff; }
  .usage-menu-diagram__row--muted { opacity: 0.55; }
  .usage-menu-diagram__tree { margin-top: 2px; }
  .usage-menu-diagram__row--parent {
    font-weight: 600; font-size: 12px;
    background: #0d1214; border-left: 3px solid #940000; color: #fff;
  }
  .usage-menu-diagram__caret { margin-left: auto; font-size: 11px; opacity: 0.85; }
  .usage-menu-diagram__sub {
    background: #2a383e; padding: 4px 0 6px;
  }
  .usage-menu-diagram__row--sub {
    padding: 7px 10px 7px 18px; font-size: 11.5px; color: rgba(255,255,255,.88);
  }
  .usage-menu-diagram__sub-icon { font-size: 11px; width: 14px; text-align: center; opacity: 0.85; }
  .usage-menu-diagram__dot { font-size: 7px; width: 14px; text-align: center; opacity: 0.55; }
  .usage-menu-diagram__row.is-target,
  .usage-menu-diagram__row--link.is-target {
    margin: 2px 6px; padding: 8px 10px 8px 12px;
    background: #940000; color: #fff !important; font-weight: 700;
    border: 2px solid #ffc107; border-radius: 6px;
    z-index: 2; text-decoration: none !important;
  }
  .usage-menu-diagram__row--link.is-target:hover { filter: brightness(1.08); color: #fff !important; }
  .usage-menu-diagram__row.is-target::after,
  .usage-menu-diagram__row--link.is-target::after {
    content: ''; position: absolute; right: -14px; top: 50%; transform: translateY(-50%);
    border: 6px solid transparent; border-left-color: #ffc107; z-index: 3;
  }
  .usage-menu-diagram__content {
    flex: 1; position: relative; background: #f4f5f7; min-height: 200px; padding: 12px 14px 12px 8px;
  }
  .usage-menu-diagram__callout {
    position: absolute; left: 0; width: 38%;
    top: var(--guide-arrow-top, 50%); transform: translateY(-50%);
    pointer-events: none; z-index: 1;
  }
  .usage-menu-diagram__callout-shaft {
    display: block; height: 5px; background: #940000; position: relative; border-radius: 2px;
  }
  .usage-menu-diagram__callout-shaft::after {
    content: ''; position: absolute; right: -8px; top: 50%; transform: translateY(-50%);
    border: 10px solid transparent; border-left: 14px solid #940000;
  }
  .usage-menu-diagram__mock-page {
    position: absolute; left: 36%; right: 10px;
    top: var(--guide-arrow-top, 50%); transform: translateY(-50%);
    background: #fff; border: 2px solid #dee2e6; border-radius: 8px;
    padding: 12px 14px; box-shadow: 0 2px 12px rgba(0,0,0,.08); z-index: 2;
  }
  .usage-menu-diagram__mock-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: .03em;
    color: #940000; font-weight: 700;
  }
  .usage-menu-diagram__mock-title { font-size: 14px; font-weight: 700; color: #212529; }
  .usage-menu-diagram__mock-hint { font-size: 12px; color: #6c757d; line-height: 1.4; }
  .usage-menu-diagram__mock-btn { font-size: 12px; }
  html.theme-dark .usage-guide-step__head { background: var(--dm-surface-2, #272d32); border-color: var(--dm-border, #363d43); }
  html.theme-dark .usage-menu-diagram__caption { color: var(--dm-text-muted, #9aa0a6); }
  html.theme-dark .usage-menu-diagram__frame { border-color: var(--dm-border, #363d43); background: var(--dm-surface, #1b2023); }
  html.theme-dark .usage-menu-diagram__sidebar { background: #1b2023; }
  html.theme-dark .usage-menu-diagram__sub { background: #272d32; }
  html.theme-dark .usage-menu-diagram__content { background: var(--dm-surface-2, #272d32); }
  html.theme-dark .usage-menu-diagram__mock-page {
    background: var(--dm-surface, #1b2023); border-color: var(--dm-border, #363d43);
  }
  html.theme-dark .usage-menu-diagram__mock-title { color: var(--dm-text, #e3e6e8); }
  html.theme-dark .usage-menu-diagram__mock-hint { color: var(--dm-text-muted, #9aa0a6); }
</style>
@endpush
