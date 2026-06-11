@extends('layouts.app')

@section('title', __('receivings.show.title').' #'.$receiving->reference_no)

@section('content')
@include('partials.official-report-styles')

@php
    $logoUrl = $business->logo_path
        ? asset('storage/'.$business->logo_path)
        : 'https://ui-avatars.com/api/?name='.urlencode($business->name).'&background=940000&color=fff&size=120';
    $stampClass = $isCancelled ? 'stamp-cancelled' : 'stamp-paid';
    $stampLabel = $isCancelled ? __('receivings.show.stamp_cancelled') : __('receivings.show.stamp_completed');
@endphp

<div class="official-report">
    <div class="app-title d-print-none">
        <div>
            <h1><i class="fa fa-truck"></i> {{ __('receivings.show.title') }} #{{ $receiving->reference_no }}</h1>
            <p>{{ __('receivings.show.subtitle') }}</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ url('/home') }}">{{ __('menu.dashboard') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('receivings.index') }}">{{ __('pages.receivings.title') }}</a></li>
            <li class="breadcrumb-item active">#{{ $receiving->reference_no }}</li>
        </ul>
        <div class="mt-2">
            <a href="{{ route('receivings.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i> {{ __('receivings.show.back') }}</a>
            @if(! $isCancelled)
            <form action="{{ route('receivings.cancel', $receiving->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm" onclick="confirmAction(event, @json(__('receivings.show.cancel_confirm_title')), @json(__('receivings.show.cancel_confirm_text')))">
                    <i class="fa fa-times"></i> {{ __('receivings.show.cancel') }}
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="tile report-sheet">
        <div class="report-header-center">
            <img src="{{ $logoUrl }}" alt="">
            <h1>{{ $business->name }}</h1>
            @if($business->phone)
            <div class="biz-contact-info">{{ $business->phone }}</div>
            @endif
            <div class="operations-title">{{ strtoupper($receiving->branch->name ?? $business->name) }} — {{ __('receivings.show.document_title') }}</div>
            <hr class="accent-divider">
        </div>

        <div class="report-stats-grid">
            <div>
                <div class="stats-row"><strong>{{ __('receivings.show.reference') }}:</strong> <span>{{ $receiving->reference_no }}</span></div>
                <div class="stats-row"><strong>{{ __('receivings.show.date') }}:</strong> <span>{{ \Carbon\Carbon::parse($receiving->received_date)->format('d M Y') }}</span></div>
                <div class="stats-row"><strong>{{ __('tables.columns.supplier') }}:</strong> <span>{{ $receiving->supplier->name ?? '—' }}</span></div>
            </div>
            <div>
                <div class="stats-row"><strong>{{ __('receivings.show.received_by') }}:</strong> <span>{{ $receiving->user->name }}</span></div>
                <div class="stats-row"><strong>{{ __('receivings.show.status') }}:</strong> <span>{{ $isCancelled ? __('tables.status.cancelled') : __('tables.status.completed') }}</span></div>
                <div class="stats-row"><strong>{{ __('receivings.show.expected_revenue') }}:</strong> <span class="amount-accent">{{ money($totals['expected_revenue']) }}</span></div>
                <div class="stats-row"><strong>{{ __('receivings.show.expected_profit') }}:</strong> <span class="{{ ($totals['expected_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ money($totals['expected_profit']) }}</span></div>
            </div>
        </div>

        <div class="title-area">
            <div class="official-stamp {{ $stampClass }}">{{ $stampLabel }}</div>
        </div>

        <div class="text-center mb-4 d-print-none">
            <button type="button" onclick="window.print()" class="btn btn-print shadow-sm mr-2">
                <i class="fa fa-print"></i> {{ __('receivings.show.print_pdf') }}
            </button>
            <a href="{{ route('receivings.show.export.pdf', $receiving) }}" class="btn btn-outline-danger btn-sm shadow-sm" style="border-color:#940000;color:#940000;">
                <i class="fa fa-file-pdf-o"></i> {{ __('receivings.show.download_pdf') }}
            </a>
        </div>

        <div class="stats-card-title mb-2">{{ __('receivings.show.items_received') }}</div>
        <div class="table-responsive">
            @include('receivings.partials.document-items-table')
        </div>

        @if($receiving->notes)
        <p class="small text-muted mt-3 mb-0"><strong>{{ __('receivings.show.notes') }}:</strong> {{ $receiving->notes }}</p>
        @endif

        <div class="mt-4 pt-3 border-top">
            <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">{{ __('price_list.staff_signature') }}</small>
            <div class="mt-3 text-muted">_______________________________________</div>
        </div>
    </div>
</div>

@include('receivings.partials.cancel-prompt-form')
@endsection

@section('scripts')
@include('receivings.partials.cancel-prompt-script')
@endsection
