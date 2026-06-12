@extends('layouts.app')

@section('title', __('stock.title'))

@section('styles')
<style>
    .font-weight-extra-bold { font-weight: 800; }
    .smallest { font-size: 11px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    .shadow-xs { box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

    .inventory-item-card {
        border-radius: 15px;
        border: 1px solid #f0f0f0;
        background: #fff;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .inventory-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
        border-color: rgba(148, 0, 0, 0.15);
    }

    .transition-all { transition: all 0.3s ease; }

    .filter-pill {
        border-radius: 20px;
        padding: 6px 16px;
        font-weight: 600;
        font-size: 11px;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .filter-pill.active {
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transform: scale(1.05);
    }

    .filter-pill[data-filter-type="category"].active { background-color: #940000; border-color: #940000; color: white !important; }
    .filter-pill[data-filter="low_stock"].active { background-color: #dc3545 !important; border-color: #dc3545 !important; color: white !important; }
    .filter-pill[data-filter-type="business"].active { background-color: #343a40 !important; border-color: #343a40 !important; color: white !important; }

    .category-tabs-wrapper {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #categoryContainer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        max-width: 100%;
    }

    .stock-filters-row > [class*="col-"] {
        min-width: 0;
    }

    .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
    .business-type-tab {
        cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
        font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
        transition: all .15s ease; line-height: 1.5;
    }
    .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
    .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
    .business-type-tab i { margin-right: 5px; }

    .product-card-wrapper { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .view-btn.active { background-color: #940000 !important; border-color: #940000 !important; color: #fff !important; }
</style>
@endsection

@section('content')
@php
  $multiBusiness = $multiBusiness ?? count($businessTypes ?? []) > 1;
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-cubes"></i> {{ __('stock.title') }}</h1>
    <p>{{ __('stock.subtitle') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">{{ __('menu.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('items.index') }}">{{ __('menu.items') }}</a></li>
    <li class="breadcrumb-item">{{ __('stock.breadcrumb_stock') }}</li>
  </ul>
</div>

<!-- Statistics -->
<div class="row">
  <div class="col-md-3 col-sm-6">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock.stats.total_items') }}</h4>
        <p><b>{{ $stats['total_items'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-exclamation-triangle fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock.stats.low_stock_items') }}</h4>
        <p><b>{{ $stats['low_stock'] }}</b> <small>{{ __('stock.stats.from_settings', ['count' => $lowStockThreshold]) }}</small></p>
      </div>
    </div>
  </div>
  @if($canViewValue)
  <div class="col-md-3 col-sm-6">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock.stats.expected_revenue') }}</h4>
        <p><b id="expectedRevenueDisplay">{{ money($totalExpectedRevenue ?? $totalValue) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>{{ __('stock.stats.expected_profit') }}</h4>
        <p><b id="expectedProfitDisplay">{{ money($totalExpectedProfit ?? $totalMargin ?? 0) }}</b></p>
      </div>
    </div>
  </div>
  @endif
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile shadow-sm border-0" style="border-radius: 15px;">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        @if($multiBusiness)
        <div class="business-type-tabs mr-3 mb-2" id="businessTypeTabs">
          <button type="button" class="business-type-tab active" data-business-type="all">
            <i class="fa fa-th-large"></i> {{ __('stock.all') }}
          </button>
          @foreach($businessTypes as $type)
          <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
            <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
          </button>
          @endforeach
        </div>
        @endif
        <div class="d-flex align-items-center flex-wrap {{ $multiBusiness ? 'ml-auto' : 'w-100 justify-content-end' }}">
          @if($stockItems->count() > 0)
          <a href="{{ route('items.stock.export.pdf') }}" class="btn btn-sm btn-outline-danger mr-2 mb-2" style="border-color:#940000;color:#940000;">
            <i class="fa fa-file-pdf-o"></i> {{ __('stock.export.pdf') }}
          </a>
          <a href="{{ route('items.stock.export.excel') }}" class="btn btn-sm btn-outline-success mr-2 mb-2">
            <i class="fa fa-file-excel-o"></i> {{ __('stock.export.excel') }}
          </a>
          @endif
          <div class="btn-group mr-2 mb-2" role="group">
            <button type="button" class="btn btn-sm btn-outline-secondary active view-btn" data-view="grid">
              <i class="fa fa-th"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary view-btn" data-view="list">
              <i class="fa fa-list"></i>
            </button>
          </div>
          @can('receive_stock')
          <a href="{{ route('receivings.create') }}" class="btn btn-primary btn-sm shadow-sm mr-2 mb-2">
            <i class="fa fa-truck"></i> {{ __('stock.new_stock_in') }}
          </a>
          @endcan
          <a href="{{ route('items.index') }}" class="btn btn-secondary btn-sm shadow-sm mb-2">
            <i class="fa fa-arrow-left"></i> {{ __('stock.back') }}
          </a>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="row mb-4 stock-filters-row">
        <div class="col-md-3">
          <div class="form-group mb-0">
            <label class="control-label font-weight-bold">{{ __('stock.search.label') }}</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
              </div>
              <input type="text" id="inventorySearch" class="form-control" placeholder="{{ __('stock.search.placeholder') }}">
            </div>
          </div>
        </div>
        <div class="col-md-9">
          <label class="control-label font-weight-bold">{{ __('stock.filters.quick_categories') }}</label>
          <div class="category-tabs-wrapper">
            <div id="categoryContainer">
              <button class="btn btn-sm btn-outline-primary active filter-pill" data-filter="all" data-filter-type="category">
                {{ __('stock.filters.all_items') }}
              </button>
              <button class="btn btn-sm btn-outline-danger filter-pill" data-filter="low_stock" data-filter-type="category">
                <i class="fa fa-exclamation-triangle"></i> {{ __('stock.filters.low_stock') }}
              </button>
              @foreach($categoryFilters as $cat)
                <button class="btn btn-sm btn-outline-primary filter-pill"
                        data-filter="{{ $cat['slug'] }}"
                        data-filter-type="category"
                        data-business-type="{{ $cat['business_type_key'] }}">
                  {{ strtoupper($cat['name']) }}
                </button>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <hr class="mb-4">

      <div class="tile-body">
        @if($stockItems->count() > 0)

          <!-- Grid View -->
          <div class="row mt-2" id="inventoryGrid">
            @foreach($stockItems as $item)
            @php
              $searchName = strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['brand'] . ' ' . $item['category']);
            @endphp
            <div class="col-md-4 col-lg-3 mb-4 product-card-wrapper"
                 data-category="{{ $item['category_slug'] }}"
                 data-business-type="{{ $item['business_type_key'] }}"
                 data-name="{{ $searchName }}"
                 data-item-id="{{ $item['id'] }}"
                 data-is-low-stock="{{ $item['is_low_stock'] ? 'true' : 'false' }}"
                 data-holding-value="{{ $item['holding_value'] }}"
                 data-expected-revenue="{{ $item['expected_revenue'] }}"
                 data-expected-profit="{{ $item['expected_profit'] }}">

              <div class="tile p-3 h-100 mb-0 shadow-sm border-0 inventory-item-card transition-all"
                   style="border-radius: 15px; {{ $item['status_color'] === 'warning' ? 'background-color: #fffde7 !important;' : '' }}">
                @if($item['is_low_stock'])
                  <div class="badge badge-warning position-absolute" style="top: 10px; right: 10px; z-index: 5;">{{ __('stock.card.low_stock') }}</div>
                @endif

                <div class="mb-2 pr-4">
                  <h6 class="font-weight-bold text-primary mb-1 line-clamp-1" title="{{ $item['name'] }}">{{ $item['name'] }}</h6>
                  <span class="badge badge-light border smallest">{{ $item['category'] }}</span>
                  @if($item['brand'])
                    <span class="text-muted smallest ml-1">{{ $item['brand'] }}</span>
                  @endif
                </div>

                <div class="mb-3">
                  <div class="bg-white border rounded p-2 shadow-xs">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="smallest text-muted text-uppercase font-weight-bold">{{ __('stock.card.available') }}</div>
                      <div class="font-weight-bold text-{{ $item['status_color'] }}">
                        {{ __('stock.card.total_on_hand', ['count' => $item['formatted_quantity']]) }}
                      </div>
                    </div>
                    @if(!empty($item['packaging_breakdown']))
                      @foreach($item['packaging_breakdown'] as $pkgStock)
                        <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'mb-1 pb-1 border-bottom' }}">
                          <div class="smallest text-muted">
                            {{ $pkgStock['name'] }}
                            @if(($pkgStock['quantity_per_unit'] ?? 1) > 1)
                              <span class="text-muted">({{ $pkgStock['quantity_per_unit'] }} {{ __('stock.card.pcs') }})</span>
                            @endif
                          </div>
                          <div class="font-weight-bold text-{{ $item['status_color'] }} text-right">
                            {{ $pkgStock['formatted_count'] }}
                          </div>
                        </div>
                      @endforeach
                    @elseif($item['has_bulk_stock'])
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div class="smallest text-muted">{{ __('stock.card.pieces') }}</div>
                          <div class="h6 mb-0 font-weight-bold text-{{ $item['status_color'] }}">
                            {{ $item['formatted_quantity'] }} {{ __('stock.card.pcs') }}
                          </div>
                        </div>
                        <div class="text-right">
                          <div class="smallest text-muted">{{ $item['stock_bulk_name'] }}</div>
                          <div class="h6 mb-0 font-weight-bold text-{{ $item['status_color'] }}">
                            {{ $item['stock_bulk_count'] }}
                          </div>
                        </div>
                      </div>
                    @else
                      <div class="h6 mb-0 font-weight-bold text-{{ $item['status_color'] }}">
                        {{ $item['stock_display'] }}
                      </div>
                    @endif
                  </div>
                </div>

                <div class="row no-gutters mb-3 text-center bg-white rounded border py-2 shadow-xs">
                  <div class="col-12">
                    @if($item['has_multi_packaging'])
                      <div class="smallest text-muted mb-1">{{ __('stock.card.selling_prices') }}</div>
                      @foreach($item['packaging_prices'] as $pkgPrice)
                        <div class="smallest {{ $loop->last ? 'mb-0' : 'mb-1' }}">
                          <strong>{{ $pkgPrice['name'] }}</strong>
                          @if($pkgPrice['quantity_per_unit'] > 1)
                            <span class="text-muted">({{ $pkgPrice['quantity_per_unit'] }} {{ __('stock.card.pcs') }})</span>
                          @endif
                          :
                          <span class="font-weight-bold {{ $pkgPrice['selling_price'] > 0 ? 'text-dark' : 'text-muted' }}">
                            {{ $pkgPrice['selling_price'] > 0 ? money($pkgPrice['selling_price']) : __('stock.card.not_set') }}
                          </span>
                        </div>
                      @endforeach
                    @else
                      <div class="smallest text-muted">{{ __('stock.card.selling_price') }}</div>
                      <div class="font-weight-bold {{ $item['selling_price'] > 0 ? 'text-dark' : 'text-muted' }}">
                        {{ $item['selling_price'] > 0 ? money($item['selling_price']) : __('stock.card.not_set') }}
                      </div>
                    @endif
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-auto flex-wrap">
                  @if($canViewValue)
                  <div class="smallest mb-2">
                    <div class="mb-1">
                      <span class="text-muted">{{ __('stock.card.expected_revenue') }}</span><br>
                      @if($item['expected_revenue'] > 0)
                        <strong class="text-primary">{{ money($item['expected_revenue']) }}</strong>
                      @else
                        <strong class="text-muted">—</strong>
                      @endif
                    </div>
                    <div>
                      <span class="text-muted">{{ __('stock.card.expected_profit') }}</span><br>
                      @if($item['expected_profit'] != 0)
                        <strong class="text-success">{{ money($item['expected_profit']) }}</strong>
                      @else
                        <strong class="text-muted">—</strong>
                      @endif
                    </div>
                  </div>
                  @endif
                  <a href="{{ $item['history_url'] }}" class="btn btn-sm btn-outline-primary ml-auto" title="{{ __('stock.card.view_history') }}">
                    <i class="fa fa-history"></i>
                  </a>
                </div>
              </div>
            </div>
            @endforeach
          </div>

          <!-- List View -->
          <div id="inventoryList" class="table-responsive d-none mt-2">
            <table class="table table-hover table-bordered shadow-sm" style="border-radius: 10px; overflow: hidden;">
              <thead class="bg-light">
                <tr>
                  <th>{{ __('tables.columns.item_name') }}</th>
                  <th>{{ __('tables.columns.brand_category') }}</th>
                  <th>{{ __('tables.columns.unit') }}</th>
                  <th>{{ __('tables.columns.current_stock') }}</th>
                  <th>{{ __('tables.columns.selling_price') }}</th>
                  @if($canViewValue)
                  <th>{{ __('stock.export.col_expected_revenue') }}</th>
                  <th>{{ __('stock.export.col_expected_profit') }}</th>
                  @endif
                  <th>{{ __('tables.columns.status') }}</th>
                  <th>{{ __('tables.columns.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($stockItems as $item)
                @php
                  $searchName = strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['brand'] . ' ' . $item['category']);
                  $statusLabel = $item['is_low_stock'] ? __('stock.status.low_stock') : __('stock.status.in_stock');
                @endphp
                <tr class="product-card-wrapper"
                    data-category="{{ $item['category_slug'] }}"
                    data-business-type="{{ $item['business_type_key'] }}"
                    data-name="{{ $searchName }}"
                    data-item-id="{{ $item['id'] }}"
                    data-is-low-stock="{{ $item['is_low_stock'] ? 'true' : 'false' }}"
                    data-holding-value="{{ $item['holding_value'] }}"
                 data-expected-revenue="{{ $item['expected_revenue'] }}"
                 data-expected-profit="{{ $item['expected_profit'] }}">
                  <td>
                    <strong class="text-primary">{{ $item['name'] }}</strong>
                  </td>
                  <td>
                    @if($item['brand'])<strong>{{ $item['brand'] }}</strong><br>@endif
                    <span class="badge badge-light border smallest text-muted">{{ $item['category'] }}</span>
                  </td>
                  <td><span class="badge badge-secondary">{{ $item['unit'] }}</span></td>
                  <td>
                    @if(!empty($item['packaging_breakdown']))
                      @foreach($item['packaging_breakdown'] as $pkgStock)
                        <div class="{{ $loop->last ? 'mb-0' : 'mb-1' }}">
                          <span class="smallest text-muted">
                            {{ $pkgStock['name'] }}
                            @if(($pkgStock['quantity_per_unit'] ?? 1) > 1)
                              <span>({{ $pkgStock['quantity_per_unit'] }} {{ __('stock.card.pcs_each') }})</span>
                            @endif
                            :
                          </span>
                          <strong class="text-{{ $item['status_color'] }}">{{ $pkgStock['formatted_count'] }}</strong>
                        </div>
                      @endforeach
                    @elseif($item['has_bulk_stock'])
                      <strong class="text-{{ $item['status_color'] }}">{{ $item['formatted_quantity'] }} {{ __('stock.card.pcs') }}</strong>
                      <br>
                      <span class="smallest text-muted">{{ $item['stock_bulk_count'] }} {{ $item['stock_bulk_name'] }}</span>
                    @else
                      <strong class="text-{{ $item['status_color'] }}">{{ $item['stock_display'] }}</strong>
                    @endif
                  </td>
                  <td>
                    @if($item['has_multi_packaging'])
                      @foreach($item['packaging_prices'] as $pkgPrice)
                        <div class="smallest {{ $loop->last ? 'mb-0' : 'mb-1' }}">
                          <strong>{{ $pkgPrice['name'] }}</strong>
                          @if($pkgPrice['quantity_per_unit'] > 1)
                            <span class="text-muted">({{ $pkgPrice['quantity_per_unit'] }} {{ __('stock.card.pcs') }})</span>
                          @endif
                          :
                          {{ $pkgPrice['selling_price'] > 0 ? money($pkgPrice['selling_price']) : __('stock.card.not_set') }}
                        </div>
                      @endforeach
                    @else
                      {{ $item['selling_price'] > 0 ? money($item['selling_price']) : __('stock.card.not_set') }}
                    @endif
                  </td>
                  @if($canViewValue)
                  <td>
                    @if($item['expected_revenue'] > 0)
                      <strong class="text-primary">{{ money($item['expected_revenue']) }}</strong>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>
                    @if($item['expected_profit'] != 0)
                      <strong class="text-success">{{ money($item['expected_profit']) }}</strong>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  @endif
                  <td><span class="badge badge-{{ $item['status_color'] }}">{{ $statusLabel }}</span></td>
                  <td class="text-center">
                    <a href="{{ $item['history_url'] }}" class="btn btn-sm btn-outline-primary" title="{{ __('stock.card.view_history') }}">
                      <i class="fa fa-history"></i>
                    </a>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if($canViewValue)
          <div class="mt-4 p-4 rounded shadow flex-wrap"
               id="totalValueBar"
               style="background: linear-gradient(135deg, #940000, #7a0000); color:white; border-radius: 15px;">
            <div class="row align-items-center w-100">
              <div class="col-md-4 mb-3 mb-md-0">
                <h5 class="mb-0 font-weight-bold"><i class="fa fa-calculator mr-2"></i> {{ __('stock.summary.totals_title') }}</h5>
                <small class="opacity-75">{{ __('stock.summary.total_value_hint') }}</small>
              </div>
              <div class="col-md-4 text-md-center mb-3 mb-md-0">
                <div class="smallest opacity-75 text-uppercase">{{ __('stock.stats.expected_revenue') }}</div>
                <h3 class="mb-0 font-weight-bold" id="totalRevenueDisplay">{{ money($totalExpectedRevenue ?? $totalValue) }}</h3>
              </div>
              <div class="col-md-4 text-md-right">
                <div class="smallest opacity-75 text-uppercase">{{ __('stock.stats.expected_profit') }}</div>
                <h3 class="mb-0 font-weight-bold" id="totalProfitDisplay">{{ money($totalExpectedProfit ?? $totalMargin ?? 0) }}</h3>
              </div>
            </div>
          </div>
          @endif

        @else
          <div class="alert alert-info py-4 text-center shadow-xs" style="border-radius: 15px;">
            <i class="fa fa-info-circle fa-2x mb-3"></i>
            @if(!empty($activeBranchName))
            <h4>{{ __('stock.empty.branch_title', ['branch' => $activeBranchName]) }}</h4>
            <p class="text-muted mb-0">{{ __('stock.empty.branch_text') }}</p>
            @else
            <h4>{{ __('stock.empty.general_title') }}</h4>
            <p class="text-muted mb-0">{{ __('stock.empty.general_text') }}</p>
            @endif
            @can('receive_stock')
            <a href="{{ route('receivings.create') }}" class="btn btn-primary mt-3"><i class="fa fa-truck"></i> {{ __('stock.record_stock_in') }}</a>
            @endcan
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const hasMultipleBusinessTypes = @json($multiBusiness);

    $('.view-btn').on('click', function() {
        const view = $(this).data('view');
        $('.view-btn').removeClass('active');
        $(this).addClass('active');

        if (view === 'grid') {
            $('#inventoryGrid').removeClass('d-none');
            $('#inventoryList').addClass('d-none');
        } else {
            $('#inventoryGrid').addClass('d-none');
            $('#inventoryList').removeClass('d-none');
        }
    });

    let activeCategory = 'all';
    let activeBusinessType = 'all';

    function syncCategoryPills() {
        $('#categoryContainer .filter-pill[data-filter-type="category"]').each(function () {
            const $pill = $(this);
            const filter = $pill.data('filter');

            if (filter === 'all' || filter === 'low_stock') {
                $pill.show();
                return;
            }

            if (!hasMultipleBusinessTypes || activeBusinessType === 'all') {
                $pill.show();
                return;
            }

            $pill.toggle(String($pill.attr('data-business-type')) === String(activeBusinessType));
        });

        const $active = $('#categoryContainer .filter-pill.active:visible');
        if ($active.length === 0) {
            activeCategory = 'all';
            $('#categoryContainer .filter-pill[data-filter="all"]').addClass('active');
        }
    }

    function updateSummaryStats() {
        let total = 0;
        let lowStock = 0;
        let revenue = 0;
        let profit = 0;

        $('.product-card-wrapper:visible').each(function () {
            total++;
            if (String($(this).data('is-low-stock')) === 'true') {
                lowStock++;
            }
            revenue += parseFloat($(this).data('expected-revenue')) || 0;
            profit += parseFloat($(this).data('expected-profit')) || 0;
        });

        $('.widget-small.primary .info p b').first().text(total);
        $('.widget-small.warning .info p b').first().text(lowStock);

        const formatMoney = (value) => 'TZS ' + value.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        if ($('#expectedRevenueDisplay').length) {
            $('#expectedRevenueDisplay').text(formatMoney(revenue));
        }
        if ($('#expectedProfitDisplay').length) {
            $('#expectedProfitDisplay').text(formatMoney(profit));
        }
        if ($('#totalRevenueDisplay').length) {
            $('#totalRevenueDisplay').text(formatMoney(revenue));
        }
        if ($('#totalProfitDisplay').length) {
            $('#totalProfitDisplay').text(formatMoney(profit));
        }
    }

    function applyFilters() {
        const searchTerm = $('#inventorySearch').val().toLowerCase();

        $('.product-card-wrapper').each(function() {
            const itemName = $(this).data('name');
            const itemCat = $(this).data('category');
            const itemBusinessType = String($(this).data('business-type') || '');
            const isLowStock = String($(this).data('is-low-stock')) === 'true';

            const matchesSearch = itemName.indexOf(searchTerm) > -1;
            const matchesBusiness = !hasMultipleBusinessTypes
                || activeBusinessType === 'all'
                || itemBusinessType === String(activeBusinessType);

            let matchesCat = false;
            if (activeCategory === 'all') {
                matchesCat = true;
            } else if (activeCategory === 'low_stock') {
                matchesCat = isLowStock;
            } else {
                matchesCat = (itemCat === activeCategory);
            }

            $(this).toggle(matchesSearch && matchesBusiness && matchesCat);
        });

        updateSummaryStats();
    }

    $('#inventorySearch').on('input', applyFilters);

    $('.filter-pill').on('click', function() {
        activeCategory = $(this).data('filter');
        $('#categoryContainer .filter-pill[data-filter-type="category"]').removeClass('active');
        $(this).addClass('active');
        applyFilters();
    });

    $('#businessTypeTabs .business-type-tab').on('click', function () {
        activeBusinessType = $(this).data('business-type');
        $('#businessTypeTabs .business-type-tab').removeClass('active');
        $(this).addClass('active');
        activeCategory = 'all';
        $('#categoryContainer .filter-pill').removeClass('active');
        $('#categoryContainer .filter-pill[data-filter="all"]').addClass('active');
        syncCategoryPills();
        applyFilters();
    });

    syncCategoryPills();
    applyFilters();
});
</script>
@endsection
