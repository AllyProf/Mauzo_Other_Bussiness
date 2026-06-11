@extends('layouts.app')

@section('title', __('menu.price_list'))

@section('content')
@include('partials.official-report-styles')

<div class="official-report">
    <div class="app-title d-print-none">
        <div>
            <h1><i class="fa fa-tags"></i> {{ __('menu.price_list') }}</h1>
            <p>{{ __('price_list.subtitle') }}</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ url('/home') }}">{{ __('menu.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('menu.price_list') }}</li>
        </ul>
    </div>

    <div class="tile d-print-none mb-3 filter-tile">
        <form method="GET" action="{{ route('price-list.index') }}" class="row align-items-end">
            <div class="col-12 col-md-4 form-group">
                <label class="small font-weight-bold mb-1">{{ __('price_list.category') }}</label>
                <select name="category_id" class="form-control form-control-sm">
                    <option value="">{{ __('price_list.all_categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) $selectedCategoryId === (int) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 form-group">
                <label class="small font-weight-bold mb-1">{{ __('price_list.search_item') }}</label>
                <input type="text" name="q" class="form-control form-control-sm" value="{{ $search }}" placeholder="{{ __('price_list.search_placeholder') }}">
            </div>
            <div class="col-12 col-md-4 form-group">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="show_unpriced" name="show_unpriced" value="1" {{ $showUnpriced ? 'checked' : '' }}>
                    <label class="custom-control-label" for="show_unpriced">{{ __('price_list.show_unpriced') }}</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="background:#940000;border-color:#940000;">
                    <i class="fa fa-filter"></i> {{ __('price_list.apply') }}
                </button>
                <a href="{{ route('price-list.index') }}" class="btn btn-outline-secondary btn-sm ml-1">{{ __('price_list.reset') }}</a>
            </div>
        </form>
    </div>

    <div class="tile report-sheet">
        @php
            $logoUrl = $business->logo_path
                ? asset('storage/'.$business->logo_path)
                : 'https://ui-avatars.com/api/?name='.urlencode($business->name).'&background=940000&color=fff&size=120';
        @endphp

        <div class="report-header-center">
            <img src="{{ $logoUrl }}" alt="{{ $business->name }}">
            <h1>{{ $business->name }}</h1>
            <div class="biz-contact-info">
                @if($business->address){{ $business->address }}@endif
                @if($business->phone) | Mobile: {{ $business->phone }}@endif
                @if($business->email) | Email: {{ $business->email }}@endif
            </div>
            @if($activeBranchName)
                <div class="operations-title">{{ strtoupper($activeBranchName) }} — {{ __('price_list.customer_price_list') }}</div>
            @else
                <div class="operations-title">{{ __('price_list.customer_price_list') }}</div>
            @endif
            <hr class="accent-divider">
        </div>

        <div class="report-sub-meta">
            <span>{{ __('price_list.prepared_by', ['name' => Auth::user()->name]) }}</span>
            <span>Ref: PRICE-{{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $business->name), 0, 3)) }}-{{ date('Ymd') }}</span>
        </div>

        <div class="title-area">
            <h2 class="main-report-title">{{ $selectedCategoryName }}</h2>
            <div class="official-stamp">{{ __('price_list.official') }}</div>
        </div>

        <div class="text-center mb-4 d-print-none">
            <button type="button" onclick="window.print()" class="btn btn-print shadow-sm">
                <i class="fa fa-print"></i> {{ __('price_list.print_pdf') }}
            </button>
            <div class="mt-2 text-muted" style="font-size:0.85rem;">
                <i class="fa fa-info-circle"></i> {{ __('price_list.print_hint') }}
            </div>
        </div>

        <div class="report-stats-grid">
            <div>
                <div class="stats-card-title">{{ __('price_list.report_information') }}</div>
                <div class="stats-row"><strong>{{ __('price_list.date') }}</strong> <span>{{ now()->format('d M Y') }}</span></div>
                <div class="stats-row"><strong>{{ __('price_list.category_filter') }}</strong> <span>{{ $selectedCategoryName }}</span></div>
                @if($search)
                <div class="stats-row"><strong>{{ __('price_list.search') }}</strong> <span>{{ $search }}</span></div>
                @endif
            </div>
            <div>
                <div class="stats-card-title">{{ __('price_list.summary') }}</div>
                <div class="stats-row"><strong>{{ __('price_list.items_listed') }}</strong> <span>{{ $totalItems }}</span></div>
                <div class="stats-row"><strong>{{ __('price_list.price_lines') }}</strong> <span>{{ $pricedPackagingCount }}</span></div>
                <div class="stats-row"><strong>{{ __('price_list.currency') }}</strong> <span>TZS</span></div>
            </div>
        </div>

        @if($grouped->isEmpty())
            <div class="alert alert-warning">
                {{ __('price_list.empty') }}
                @if(! $showUnpriced)
                    {!! __('price_list.empty_hint') !!}
                @endif
            </div>
        @else
            <div class="stats-card-title mb-2">{{ __('price_list.selling_prices') }}</div>
            <div class="table-responsive">
                <table class="report-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th class="text-left">{{ __('price_list.item_name') }}</th>
                            <th style="width:110px;">{{ __('price_list.brand') }}</th>
                            <th style="width:120px;">{{ __('price_list.pack_unit') }}</th>
                            <th style="width:90px;">{{ __('price_list.pack_size') }}</th>
                            <th style="width:130px;">{{ __('price_list.selling_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 1; @endphp
                        @foreach($grouped as $categoryName => $categoryItems)
                            <tr class="category-row">
                                <td colspan="6">{{ $categoryName }}</td>
                            </tr>
                            @foreach($categoryItems as $item)
                                @foreach($item['packaging_rows'] as $packIndex => $pack)
                                    <tr>
                                        @if($packIndex === 0)
                                            <td rowspan="{{ count($item['packaging_rows']) }}" class="text-muted-row">{{ $rowNum++ }}</td>
                                            <td rowspan="{{ count($item['packaging_rows']) }}" class="text-left">
                                                {{ $item['name'] }}
                                                @if($item['sku'])
                                                    <br><small class="text-muted font-weight-normal">SKU: {{ $item['sku'] }}</small>
                                                @endif
                                            </td>
                                            <td rowspan="{{ count($item['packaging_rows']) }}">{{ $item['brand'] ?: '—' }}</td>
                                        @endif
                                        <td><span class="packaging-badge">{{ $pack['label'] }}</span></td>
                                        <td>
                                            @if($pack['quantity_per_unit'] > 1)
                                                {{ $pack['quantity_per_unit'] }} {{ __('price_list.pcs') }}
                                            @else
                                                1 {{ __('price_list.pc') }}
                                            @endif
                                        </td>
                                        <td class="amount-accent">
                                            @if($pack['selling_price'] > 0)
                                                {{ money($pack['selling_price']) }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-4 pt-4 border-top row">
            <div class="col-md-6">
                <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">{{ __('price_list.staff_signature') }}</small>
                <div class="mt-3 text-muted">_______________________________________</div>
            </div>
            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">{{ __('price_list.customer_copy') }}</small>
                <div class="mt-3 text-muted">_______________________________________</div>
            </div>
        </div>

        <div class="text-center mt-4 small text-muted">
            {{ __('price_list.footer', ['datetime' => now()->format('d M Y, H:i')]) }}
        </div>
    </div>
</div>
@endsection
