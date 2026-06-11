@extends('layouts.app')

@section('title', __('pages.receivings.title'))

@section('styles')
<style>
  .receivings-page .receiving-ref-cell strong { display: block; line-height: 1.35; }
  .receivings-page .receiving-mobile-meta { margin-top: 5px; line-height: 1.45; }
  .receivings-page .receiving-actions { white-space: nowrap; }
  .receivings-page .receiving-actions .btn { padding: 0.35rem 0.5rem; }

  @media (max-width: 991.98px) {
    .receivings-page .app-title { flex-wrap: wrap; align-items: flex-start !important; gap: 10px; }
    .receivings-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .receivings-page .app-title p { display: block !important; font-size: 0.88rem; font-style: normal; }
    .receivings-page .app-title > .btn { width: 100%; margin-left: 0 !important; }
    .receivings-page .tile-title-w-btn { flex-direction: column; align-items: stretch !important; }
    .receivings-page .tile-title-w-btn .btn-group { margin-top: 8px; }
  }

  @media (max-width: 767.98px) {
    .receivings-page .receivings-col-hide-mobile { display: none !important; }
    .receivings-page #receivingsTable { font-size: 13px; }
    .receivings-page #receivingsTable thead th { font-size: 11px; }
    .receivings-page .receivings-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .receivings-page .receiving-actions { display: flex; gap: 4px; justify-content: center; }
    .receivings-page .receiving-actions form { display: inline-block !important; }
  }
</style>
@endsection

@section('content')
@php
  $activePeriod = $dateFilter['period'] ?? 'all';
  $exportQuery = request()->only(['period', 'start_date', 'end_date', 'status', 'business_type']);
@endphp

<div class="receivings-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-truck"></i> {{ __('pages.receivings.title') }}</h1>
    <p>{{ __('pages.receivings.subtitle') }}</p>
  </div>
  @can('receive_stock')
  <a href="{{ route('receivings.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('pages.receivings.new_stock_in') }}</a>
  @endcan
</div>

@if($multiBusiness ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>{{ __('pages.receivings.multi_department') }}</strong> {{ __('pages.receivings.multi_department_hint') }}
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="tile-title">{{ __('pages.receivings.title') }} <small class="text-muted" id="receivingResultCount">({{ __('tables.counts.records', ['count' => $receivings->count()]) }})</small></h3>
        <p class="mb-0">
          @if($receivings->count() > 0)
          <a href="{{ route('receivings.export.pdf', $exportQuery) }}" class="btn btn-outline-danger btn-sm mr-1">
            <i class="fa fa-file-pdf-o"></i> {{ __('receivings.export.pdf') }}
          </a>
          <a href="{{ route('receivings.export.excel', $exportQuery) }}" class="btn btn-outline-success btn-sm">
            <i class="fa fa-file-excel-o"></i> {{ __('receivings.export.excel') }}
          </a>
          @endif
        </p>
      </div>
      <div class="tile-body">
        <div class="row align-items-end mb-3">
          <div class="col-lg-4 col-md-5 form-group mb-lg-0">
            <label class="control-label">{{ __('tables.filters.search') }}</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
              </div>
              <input type="text" id="receivingSearch" class="form-control" placeholder="{{ __('tables.filters.search') }}...">
            </div>
          </div>
          <div class="col-lg-8 col-md-7">
            <form method="GET" action="{{ route('receivings.index') }}" id="receivingsFilterForm" class="row align-items-end">
              <div class="col-lg-2 col-md-4 form-group mb-lg-0">
                <label class="control-label">{{ __('receivings.filters.period') }}</label>
                <select name="period" id="periodSelect" class="form-control">
                  @foreach(['all', 'today', 'weekly', 'monthly', 'yearly', 'custom'] as $periodKey)
                    <option value="{{ $periodKey }}" {{ $activePeriod === $periodKey ? 'selected' : '' }}>
                      {{ __('receivings.period.'.$periodKey) }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-2 col-md-4 form-group mb-lg-0 custom-date-fields" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
                <label class="control-label">{{ __('receivings.filters.from_date') }}</label>
                <input type="date" name="start_date" class="form-control" value="{{ $dateFilter['from'] ?? request('start_date') }}">
              </div>
              <div class="col-lg-2 col-md-4 form-group mb-lg-0 custom-date-fields" style="{{ $activePeriod === 'custom' ? '' : 'display:none;' }}">
                <label class="control-label">{{ __('receivings.filters.to_date') }}</label>
                <input type="date" name="end_date" class="form-control" value="{{ $dateFilter['to'] ?? request('end_date') }}">
              </div>
              <div class="col-lg-2 col-md-4 form-group mb-lg-0">
                <label class="control-label">{{ __('tables.filters.status') }}</label>
                <select name="status" class="form-control">
                  <option value="all" {{ empty($statusFilter) ? 'selected' : '' }}>{{ __('tables.filters.all_statuses') }}</option>
                  <option value="completed" {{ ($statusFilter ?? '') === 'completed' ? 'selected' : '' }}>{{ __('tables.filters.completed') }}</option>
                  <option value="cancelled" {{ ($statusFilter ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('tables.filters.cancelled') }}</option>
                </select>
              </div>
              @if($multiBusiness ?? false)
              <div class="col-lg-2 col-md-4 form-group mb-lg-0">
                <label class="control-label">{{ __('receivings.filters.business_type') }}</label>
                <select name="business_type" class="form-control">
                  <option value="all" {{ empty($businessTypeFilter) || $businessTypeFilter === 'all' ? 'selected' : '' }}>{{ __('tables.filters.all') }}</option>
                  @foreach($businessTypes as $type)
                    <option value="{{ $type['key'] }}" {{ ($businessTypeFilter ?? '') === $type['key'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                  @endforeach
                </select>
              </div>
              @endif
              <div class="col-lg-{{ ($multiBusiness ?? false) ? '2' : '4' }} col-md-4 form-group mb-lg-0 d-flex">
                <button type="submit" class="btn btn-primary mr-2"><i class="fa fa-filter"></i> {{ __('receivings.filters.apply') }}</button>
                <a href="{{ route('receivings.index') }}" class="btn btn-secondary">{{ __('receivings.filters.reset') }}</a>
              </div>
            </form>
          </div>
        </div>

        <div class="table-responsive receivings-table-wrap">
          <table class="table table-hover table-bordered mb-0" id="receivingsTable">
            <thead>
              <tr>
                <th>{{ __('tables.columns.ref_no') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.branch') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.supplier') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.date') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.total_amount') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.received_by') }}</th>
                <th class="receivings-col-hide-mobile">{{ __('tables.columns.status') }}</th>
                <th>{{ __('tables.columns.action') }}</th>
              </tr>
            </thead>
            <tbody id="receivingsTableBody">
              @foreach($receivings as $receiving)
                @php
                  $status = ($receiving->status ?? 'completed') === 'cancelled' ? 'cancelled' : 'completed';
                  $searchText = strtolower(implode(' ', array_filter([
                    $receiving->reference_no,
                    $receiving->branch->name ?? '',
                    $receiving->supplier->name ?? '',
                    $receiving->user->name ?? '',
                    \Carbon\Carbon::parse($receiving->received_date)->format('M d, Y'),
                    number_format($receiving->total_amount, 2),
                    $status,
                  ])));
                @endphp
                <tr class="receiving-row" data-search="{{ $searchText }}">
                  <td>
                    <div class="receiving-ref-cell">
                      <strong>{{ $receiving->reference_no }}</strong>
                      <div class="d-md-none receiving-mobile-meta">
                        <small class="text-muted d-block">{{ $receiving->supplier->name ?? __('tables.misc.not_available') }}</small>
                        <small class="text-muted d-block">
                          {{ $receiving->branch->name ?? __('tables.misc.dash') }}
                          · {{ \Carbon\Carbon::parse($receiving->received_date)->format('M d, Y') }}
                        </small>
                        <small class="d-block font-weight-bold text-dark">TZS {{ number_format($receiving->total_amount, 2) }}</small>
                        <small class="text-muted d-block">{{ $receiving->user->name }}</small>
                        @if($status === 'cancelled')
                          <span class="badge badge-secondary mt-1">{{ __('tables.status.cancelled') }}</span>
                        @else
                          <span class="badge badge-success mt-1">{{ __('tables.status.completed') }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td class="receivings-col-hide-mobile">{{ $receiving->branch->name ?? __('tables.misc.dash') }}</td>
                  <td class="receivings-col-hide-mobile">{{ $receiving->supplier->name ?? __('tables.misc.not_available') }}</td>
                  <td class="receivings-col-hide-mobile">{{ \Carbon\Carbon::parse($receiving->received_date)->format('M d, Y') }}</td>
                  <td class="receivings-col-hide-mobile">TZS {{ number_format($receiving->total_amount, 2) }}</td>
                  <td class="receivings-col-hide-mobile">{{ $receiving->user->name }}</td>
                  <td class="receivings-col-hide-mobile">
                    @if($status === 'cancelled')
                      <span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
                    @else
                      <span class="badge badge-success">{{ __('tables.status.completed') }}</span>
                    @endif
                  </td>
                  <td class="text-center receiving-actions">
                    <a href="{{ route('receivings.show', $receiving->id) }}" class="btn btn-sm btn-info" title="{{ __('tables.actions.view') }}">
                      <i class="fa fa-eye"></i>
                    </a>
                    @if($status !== 'cancelled')
                    <form action="{{ route('receivings.cancel', $receiving->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-danger" title="{{ __('tables.actions.cancel') }}" onclick="confirmAction(event, @json(__('tables.actions.cancel_receiving')), @json(__('tables.actions.cancel_receiving_text')))"><i class="fa fa-times"></i></button>
                    </form>
                    @endif
                  </td>
                </tr>
              @endforeach
              @if($receivings->isEmpty())
                <tr id="receivingsEmptyRow">
                  <td colspan="8" class="text-center">{{ __('tables.empty.receiving_records') }}</td>
                </tr>
              @endif
              <tr id="receivingsNoMatchRow" style="display:none;">
                <td colspan="8" class="text-center text-muted py-4">{{ __('tables.empty.no_match') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

@include('receivings.partials.cancel-prompt-form')
@endsection

@section('scripts')
@include('receivings.partials.cancel-prompt-script')
<script>
$(function () {
  var $rows = $('.receiving-row');
  var $noMatch = $('#receivingsNoMatchRow');
  var $empty = $('#receivingsEmptyRow');
  var $count = $('#receivingResultCount');
  var recordsShownLabel = @json(__('tables.counts.records'));

  $('#periodSelect').on('change', function () {
    if ($(this).val() === 'custom') {
      $('.custom-date-fields').show();
    } else {
      $('.custom-date-fields').hide();
    }
  });

  function filterReceivings() {
    var term = ($('#receivingSearch').val() || '').toLowerCase().trim();
    var visible = 0;

    $rows.each(function () {
      var $row = $(this);
      var show = !term || String($row.attr('data-search')).indexOf(term) > -1;
      $row.toggle(show);
      if (show) visible++;
    });

    if ($rows.length === 0) {
      $noMatch.hide();
      return;
    }

    if ($empty.length) {
      $empty.hide();
    }

    $noMatch.toggle(visible === 0);
    $count.text('(' + recordsShownLabel.replace(':count', visible) + ')');
  }

  $('#receivingSearch').on('input', filterReceivings);
});
</script>
@endsection
