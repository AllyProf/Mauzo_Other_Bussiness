@extends('layouts.app')

@section('title', __('live_sales.title'))

@push('styles')
<style>
    .velocity-chart-container {
        height: 250px;
    }
    .refresh-bar-container {
        position: fixed;
        top: 50px;
        left: 0;
        width: 100%;
        height: 3px;
        z-index: 9999;
        background: transparent;
    }
    #refresh-progress {
        height: 100%;
        background: #940000;
        width: 100%;
        transition: width 0.1s linear;
    }
    .widget-small .info h4 {
        text-transform: uppercase;
        font-size: 11px;
        margin-bottom: 5px;
        font-weight: 600;
    }
    .live-feed-item:first-child {
        animation: liveFeedFlash 1.5s ease-out;
    }
    @keyframes liveFeedFlash {
        from { background-color: rgba(148, 0, 0, 0.12); }
        to { background-color: transparent; }
    }

    .live-sales-page .pulse-filter-tabs {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .live-sales-page .pulse-filter-tabs::-webkit-scrollbar { display: none; }

    @media (max-width: 991.98px) {
        .live-sales-page .app-title {
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }
        .live-sales-page .app-title h1 {
            font-size: 1.35rem;
            line-height: 1.35;
        }
        .live-sales-page .app-title p {
            display: block !important;
            font-size: 0.88rem;
            font-style: normal;
        }
        .live-sales-page .app-breadcrumb {
            width: 100%;
            margin-top: 0;
        }
        .live-sales-page .pulse-filter-tabs {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .live-sales-page .app-title h1 {
            font-size: 1.15rem;
        }
        .live-sales-page .app-title p {
            font-size: 0.82rem;
        }
        .live-sales-page .tile {
            padding: 14px;
        }
        .live-sales-page .widget-small {
            height: auto;
            min-height: 88px;
            margin-bottom: 12px;
        }
        .live-sales-page .widget-small .icon {
            width: 52px;
            min-width: 52px;
            line-height: 1;
            font-size: 26px;
            padding: 12px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .live-sales-page .widget-small .icon .fa-3x {
            font-size: 1.6em;
        }
        .live-sales-page .widget-small .info {
            padding: 10px 12px 10px 8px;
            min-width: 0;
        }
        .live-sales-page .widget-small .info h4 {
            font-size: 0.68rem;
        }
        .live-sales-page .widget-small .info p {
            font-size: 0.92rem;
            word-break: break-word;
        }
        .live-sales-page .widget-small .info small {
            font-size: 0.65rem;
            line-height: 1.35;
            display: block;
        }
        .live-sales-page .velocity-chart-container {
            height: 210px;
        }
        .live-sales-page .live-chart-doughnut {
            width: 150px !important;
            height: 150px !important;
        }
        .live-sales-page .live-chart-tile {
            min-height: 220px !important;
        }
        .live-sales-page #live-feed-container {
            max-height: 420px;
        }
        .live-sales-page .live-feed-item {
            flex-direction: row;
            align-items: flex-start;
        }
        .live-sales-page .live-feed-item .live-feed-side {
            min-width: 48px !important;
            margin-right: 10px !important;
        }
        .live-sales-page .live-feed-item .live-feed-main .live-feed-top {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .live-sales-page .live-feed-item .live-feed-amount {
            margin-left: 0 !important;
            margin-top: 4px;
            white-space: normal !important;
        }
        .live-sales-page #staff-pulse-container .list-group-item {
            flex-wrap: wrap;
            gap: 6px;
        }
        .live-sales-page #staff-pulse-container .badge-pill {
            margin-left: auto;
        }
        .live-sales-page .trending-row {
            flex-wrap: wrap;
            gap: 4px;
        }
        .live-sales-page .trending-row .small {
            max-width: 75%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }

    @media (max-width: 575.98px) {
        .live-sales-page .live-kpi-col {
            padding-left: 8px;
            padding-right: 8px;
        }
        .live-sales-page .widget-small .info p {
            font-size: 0.85rem;
        }
    }
</style>
@endpush

@section('content')
<div class="live-sales-page">
<div class="refresh-bar-container">
    <div id="refresh-progress"></div>
</div>

<div class="app-title">
    <div>
        <h1><i class="fa fa-bolt"></i> {{ $activeShift ? __('live_sales.live_shift_pulse') : __('live_sales.daily_sales_monitor') }}</h1>
        <p>
            @if($pulseMode === 'none')
                <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> {{ $scopeLabel }}</span>
            @elseif($activeShift)
                {{ __('live_sales.monitoring_shift', ['id' => $activeShift->id, 'time' => $activeShift->opened_at->format('H:i')]) }}
            @else
                {!! __('live_sales.pulse_for', ['scope' => '<strong>'.$scopeLabel.'</strong>', 'date' => now()->translatedFormat('l, F j')]) !!}
            @endif
            @if(!empty($filterNote))
            <br><small class="text-muted" id="pulse-filter-note"><i class="fa fa-filter"></i> {{ $filterNote }}</small>
            @endif
        </p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="{{ route('live-sales.index') }}">{{ __('live_sales.live_monitor') }}</a></li>
        <li class="breadcrumb-item" id="last-updated-text" style="font-weight: bold; color: #940000;">{{ __('live_sales.synced_now') }}</li>
    </ul>
</div>

@include('live-sales.partials.filters')

<div class="row live-kpi-row">
    <div class="col-6 col-md-3 live-kpi-col">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-money fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? __('live_sales.stats.shift_revenue') : __('live_sales.stats.today_revenue') }}</h4>
                <p><b id="total-revenue-text">{{ money($totalRevenue) }}</b></p>
                <small>{{ __('live_sales.stats.cash') }}: <span id="cash-revenue-text">{{ money($todayCash, false) }}</span> | {{ __('live_sales.stats.digital') }}: <span id="digital-revenue-text">{{ money($todayDigital, false) }}</span></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 live-kpi-col">
        <div class="widget-small info coloured-icon" style="background-color: #28a745 !important;">
            <i class="icon fa fa-line-chart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? __('live_sales.stats.shift_profit') : __('live_sales.stats.gross_profit') }}</h4>
                <p><b id="total-profit-text">{{ money($shiftProfit) }}</b></p>
                <small class="text-white" id="margin-text">{{ __('live_sales.stats.margin', ['percent' => $marginPercent]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 live-kpi-col">
        <div class="widget-small warning coloured-icon">
            <i class="icon fa fa-refresh fa-3x"></i>
            <div class="info">
                <h4>{{ __('live_sales.stats.in_circulation') }}</h4>
                <p><b id="total-circulation-text">{{ money($moneyInCirculation) }}</b></p>
                <small>{{ __('live_sales.stats.circulation_hint') }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 live-kpi-col">
        <div class="widget-small danger coloured-icon">
            <i class="icon fa fa-shopping-cart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? __('live_sales.stats.shift_orders') : __('live_sales.stats.today_orders') }}</h4>
                <p>
                    <b id="total-orders-count">{{ $totalOrders }}</b> <small>{{ __('live_sales.stats.total') }}</small> |
                    <b class="text-white" id="active-orders-count">{{ $activeOrders }}</b> <small>{{ __('live_sales.stats.open') }}</small>
                </p>
                <small>{{ __('live_sales.stats.paid') }}: <span id="served-orders-count">{{ $servedOrders }}</span></small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4 mb-md-0">
        <div class="tile p-3 mb-4 live-chart-tile" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-muted small font-weight-bold text-uppercase">{{ $activeShift ? __('live_sales.charts.shift_velocity') : __('live_sales.charts.hourly_velocity') }}</h6>
                <span class="badge badge-primary">{{ __('live_sales.charts.sales_per_hour') }}</span>
            </div>
            <div class="velocity-chart-container flex-grow-1">
                <canvas id="velocityChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="tile p-3 mb-4 live-chart-tile" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h6 class="mb-0 text-muted small font-weight-bold text-uppercase">{{ __('live_sales.charts.sales_mix') }}</h6>
                <span class="badge badge-info mt-1 mt-md-0">{{ __('live_sales.charts.products_vs_services') }}</span>
            </div>
            <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                <div class="live-chart-doughnut" style="width: 180px; height: 180px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="tile">
            <h3 class="tile-title border-bottom pb-2">
                <i class="fa fa-flash text-warning mr-2"></i> {{ __('live_sales.feed.title') }}
            </h3>
            <div class="tile-body" id="live-feed-container" style="max-height: 600px; overflow-y: auto;">
                @include('live-sales.partials.feed_items', ['liveFeed' => $liveFeed])
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="tile mb-4">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-users text-primary mr-2"></i> {{ __('live_sales.staff.leaderboard') }}</h3>
            <div class="tile-body">
                <ul class="list-group list-group-flush" id="staff-pulse-container">
                    @include('live-sales.partials.staff_items', ['staffPulse' => $staffPulse])
                </ul>
            </div>
        </div>
        <div class="tile">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-star text-warning mr-2"></i> {{ __('live_sales.trending.title') }}</h3>
            <div class="tile-body">
                <div class="mb-3">
                    <h6 class="text-muted small font-weight-bold mb-3">{{ __('live_sales.trending.top_products') }}</h6>
                    @forelse($topProducts as $product)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1 trending-row">
                        <span class="small font-weight-bold">{{ $product->name }}</span>
                        <span class="badge badge-pill badge-primary">{{ number_format($product->total_qty, 0) }}</span>
                    </div>
                    @empty
                    <p class="small text-muted mb-0">{{ __('live_sales.trending.no_products') }}</p>
                    @endforelse
                </div>
                <hr>
                <div>
                    <h6 class="text-muted small font-weight-bold mb-3">{{ __('live_sales.trending.top_services') }}</h6>
                    @forelse($topServices as $service)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1 trending-row">
                        <span class="small">{{ $service->name }}</span>
                        <span class="badge badge-pill badge-info">{{ number_format($service->total_qty, 0) }}</span>
                    </div>
                    @empty
                    <p class="small text-muted mb-0">{{ __('live_sales.trending.no_services') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
@php
    $pulseI18n = [
        'sales' => __('live_sales.charts.sales'),
        'products' => __('live_sales.charts.products'),
        'services' => __('live_sales.charts.services'),
        'synced_at' => __('live_sales.synced_at'),
        'margin' => __('live_sales.stats.margin'),
    ];
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var pulseI18n = @json($pulseI18n);
    var velocityChart, categoryChart;
    var refreshInterval = 30000;
    var lastRefresh = Date.now();
    var feedUrl = @json(route('live-sales.index'));
    var filterQuery = @json($filterQuery ?? []);
    var isMobileChart = window.matchMedia('(max-width: 767.98px)').matches;

    function initCharts() {
        var velCtx = document.getElementById('velocityChart').getContext('2d');
        velocityChart = new Chart(velCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, function (_, i) { return i + ':00'; }),
                datasets: [{
                    label: pulseI18n.sales,
                    data: @json(array_values($hourlyData)),
                    borderColor: '#940000',
                    backgroundColor: 'rgba(148, 0, 0, 0.05)',
                    borderWidth: isMobileChart ? 2 : 3,
                    pointRadius: isMobileChart ? 1 : 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            color: '#666',
                            font: { size: isMobileChart ? 9 : 10 },
                            maxRotation: isMobileChart ? 0 : 0,
                            autoSkip: true,
                            maxTicksLimit: isMobileChart ? 8 : 12
                        },
                        grid: { display: false }
                    },
                    y: {
                        ticks: { precision: 0, font: { size: isMobileChart ? 9 : 11 }, maxTicksLimit: isMobileChart ? 5 : 8 },
                        grid: { borderDash: [5, 5] }
                    }
                }
            }
        });

        var catCtx = document.getElementById('categoryChart').getContext('2d');
        categoryChart = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: [pulseI18n.products, pulseI18n.services],
                datasets: [{
                    data: [{{ $categoryMix['products'] }}, {{ $categoryMix['services'] }}],
                    backgroundColor: ['#940000', '#17a2b8'],
                    hoverOffset: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: isMobileChart ? 10 : 12, font: { size: isMobileChart ? 10 : 11 }, padding: isMobileChart ? 8 : 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': TZS ' + Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }

    function pulseFeedUrl() {
        var params = new URLSearchParams();
        Object.keys(filterQuery || {}).forEach(function (key) {
            if (filterQuery[key] !== null && filterQuery[key] !== undefined && filterQuery[key] !== '') {
                params.set(key, filterQuery[key]);
            }
        });
        var qs = params.toString();
        return feedUrl + (qs ? '?' + qs : '');
    }

    function updateDashboard() {
        fetch(pulseFeedUrl(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            document.getElementById('total-revenue-text').innerText = 'TZS ' + data.revenue.total;
            document.getElementById('cash-revenue-text').innerText = data.revenue.cash;
            document.getElementById('digital-revenue-text').innerText = data.revenue.digital;
            document.getElementById('total-profit-text').innerText = 'TZS ' + data.revenue.profit;
            document.getElementById('total-circulation-text').innerText = 'TZS ' + data.revenue.circulation;
            document.getElementById('margin-text').innerText = pulseI18n.margin.replace(':percent', data.margin_percent);

            document.getElementById('total-orders-count').innerText = data.pulse.total_orders;
            document.getElementById('active-orders-count').innerText = data.pulse.active_orders;
            document.getElementById('served-orders-count').innerText = data.pulse.served_orders;

            document.getElementById('live-feed-container').innerHTML = data.live_feed;
            document.getElementById('staff-pulse-container').innerHTML = data.staff_pulse;

            velocityChart.data.datasets[0].data = data.hourly_data;
            velocityChart.update('none');

            categoryChart.data.datasets[0].data = [data.category_mix.products, data.category_mix.services];
            categoryChart.update('none');

            document.getElementById('last-updated-text').innerText = pulseI18n.synced_at.replace(':time', new Date().toLocaleTimeString());
            lastRefresh = Date.now();
        })
        .catch(function (err) {
            console.error('Live sales sync error:', err);
        });
    }

    function updateProgressBar() {
        var now = Date.now();
        var elapsed = now - lastRefresh;
        var remaining = Math.max(0, refreshInterval - elapsed);
        var percentage = (remaining / refreshInterval) * 100;
        document.getElementById('refresh-progress').style.width = percentage + '%';

        if (elapsed >= refreshInterval) {
            updateDashboard();
        } else {
            requestAnimationFrame(updateProgressBar);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCharts();
        updateProgressBar();
    });
})();
</script>
@endpush
