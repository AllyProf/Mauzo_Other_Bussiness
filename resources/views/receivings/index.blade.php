@extends('layouts.app')

@section('title', __('pages.receivings.title'))

@section('styles')
<style>
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
</style>
@endsection

@section('content')
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

@if(!empty($activeBranchName))
<div class="alert alert-info mb-3 py-2">
  <i class="fa fa-map-marker"></i>
  {{ __('pages.receivings.showing_branch') }} <strong>{{ $activeBranchName }}</strong>.
</div>
@elseif($viewingAllBranches ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-building"></i>
  {{ __('pages.receivings.viewing_all_branches') }}
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      @if($multiBusiness ?? false)
      <div class="business-type-tabs mb-3" id="businessTypeTabs">
        <button type="button" class="business-type-tab active" data-business-type="all">
          <i class="fa fa-th-large"></i> {{ __('tables.filters.all') }}
        </button>
        @foreach($businessTypes as $type)
        <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
          <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
        </button>
        @endforeach
      </div>
      @endif

      <div class="row mb-3">
        <div class="col-md-5">
          <label class="control-label font-weight-bold small text-uppercase text-muted">{{ __('tables.filters.search') }}</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fa fa-search"></i></span>
            </div>
            <input type="text" id="receivingSearch" class="form-control" placeholder="{{ __('tables.filters.search') }}...">
          </div>
        </div>
        <div class="col-md-3">
          <label class="control-label font-weight-bold small text-uppercase text-muted">{{ __('tables.filters.status') }}</label>
          <select id="receivingStatusFilter" class="form-control">
            <option value="all">{{ __('tables.filters.all_statuses') }}</option>
            <option value="completed">{{ __('tables.filters.completed') }}</option>
            <option value="cancelled">{{ __('tables.filters.cancelled') }}</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <small class="text-muted" id="receivingResultCount">{{ __('tables.counts.records', ['count' => $receivings->count()]) }}</small>
        </div>
      </div>

      <div class="tile-body px-0 pt-0">
        <table class="table table-hover table-bordered mb-0" id="receivingsTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.ref_no') }}</th>
              <th>{{ __('tables.columns.branch') }}</th>
              <th>{{ __('tables.columns.supplier') }}</th>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.total_amount') }}</th>
              <th>{{ __('tables.columns.received_by') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>{{ __('tables.columns.action') }}</th>
            </tr>
          </thead>
          <tbody id="receivingsTableBody">
            @foreach($receivings as $receiving)
                @php
                  $status = ($receiving->status ?? 'completed') === 'cancelled' ? 'cancelled' : 'completed';
                  $businessTypeKeys = $receiving->items
                      ->map(fn ($line) => $line->item?->category?->source_business_type_key ?: 'other')
                      ->unique()
                      ->values();
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
                <tr class="receiving-row"
                    data-search="{{ $searchText }}"
                    data-status="{{ $status }}"
                    data-business-types="{{ $businessTypeKeys->implode(',') }}">
                    <td><strong>{{ $receiving->reference_no }}</strong></td>
                    <td>{{ $receiving->branch->name ?? __('tables.misc.dash') }}</td>
                    <td>{{ $receiving->supplier->name ?? __('tables.misc.not_available') }}</td>
                    <td>{{ \Carbon\Carbon::parse($receiving->received_date)->format('M d, Y') }}</td>
                    <td>TZS {{ number_format($receiving->total_amount, 2) }}</td>
                    <td>{{ $receiving->user->name }}</td>
                    <td>
                        @if($status === 'cancelled')
                            <span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
                        @else
                            <span class="badge badge-success">{{ __('tables.status.completed') }}</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
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
  var hasMultipleBusinessTypes = @json($multiBusiness ?? false);
  var activeBusinessType = 'all';
  var recordsShownLabel = @json(__('tables.counts.records_shown'));

  function rowMatchesBusinessType($row) {
    if (!hasMultipleBusinessTypes || activeBusinessType === 'all') {
      return true;
    }

    var keys = String($row.attr('data-business-types') || '').split(',').filter(Boolean);

    return keys.indexOf(String(activeBusinessType)) !== -1;
  }

  function filterReceivings() {
    var term = ($('#receivingSearch').val() || '').toLowerCase().trim();
    var status = $('#receivingStatusFilter').val() || 'all';
    var visible = 0;

    $rows.each(function () {
      var $row = $(this);
      var matchesSearch = !term || String($row.attr('data-search')).indexOf(term) > -1;
      var matchesStatus = status === 'all' || String($row.attr('data-status')) === status;
      var matchesBusiness = rowMatchesBusinessType($row);
      var show = matchesSearch && matchesStatus && matchesBusiness;
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

    if (visible === 0) {
      $noMatch.show();
    } else {
      $noMatch.hide();
    }

    $count.text(recordsShownLabel.replace(':count', visible));
  }

  $('#receivingSearch').on('input', filterReceivings);
  $('#receivingStatusFilter').on('change', filterReceivings);

  $('#businessTypeTabs .business-type-tab').on('click', function () {
    $('#businessTypeTabs .business-type-tab').removeClass('active');
    $(this).addClass('active');
    activeBusinessType = String($(this).attr('data-business-type') || 'all');
    filterReceivings();
  });
});
</script>
@endsection
