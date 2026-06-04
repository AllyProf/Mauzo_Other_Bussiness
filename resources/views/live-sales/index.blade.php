@extends('layouts.app')

@section('title', 'Live Sales Pulse')

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
</style>
@endpush

@section('content')
<div class="refresh-bar-container">
    <div id="refresh-progress"></div>
</div>

<div class="app-title">
    <div>
        <h1><i class="fa fa-bolt"></i> {{ $activeShift ? 'Live Shift Pulse' : 'Daily Sales Monitor' }}</h1>
        <p>
            @if($pulseMode === 'none')
                <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> {{ $scopeLabel }}</span>
            @elseif($activeShift)
                Monitoring <strong>Shift #{{ $activeShift->id }}</strong> (started {{ $activeShift->opened_at->format('H:i') }})
            @else
                Real-time pulse for <strong>{{ $scopeLabel }}</strong> — {{ now()->format('l, F j') }}
            @endif
            @if(!empty($filterNote))
            <br><small class="text-muted" id="pulse-filter-note"><i class="fa fa-filter"></i> {{ $filterNote }}</small>
            @endif
        </p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="{{ route('live-sales.index') }}">Live Monitor</a></li>
        <li class="breadcrumb-item" id="last-updated-text" style="font-weight: bold; color: #940000;">Synced: Just now</li>
    </ul>
</div>

@include('live-sales.partials.filters')

<div class="row">
    <div class="col-md-3">
        <div class="widget-small primary coloured-icon">
            <i class="icon fa fa-money fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Revenue' : 'Today Revenue' }}</h4>
                <p><b id="total-revenue-text">{{ money($totalRevenue) }}</b></p>
                <small>Cash: <span id="cash-revenue-text">{{ money($todayCash, false) }}</span> | Digital: <span id="digital-revenue-text">{{ money($todayDigital, false) }}</span></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small info coloured-icon" style="background-color: #28a745 !important;">
            <i class="icon fa fa-line-chart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Profit' : 'Gross Profit' }}</h4>
                <p><b id="total-profit-text">{{ money($shiftProfit) }}</b></p>
                <small class="text-white" id="margin-text">Margin: {{ $marginPercent }}%</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small warning coloured-icon">
            <i class="icon fa fa-refresh fa-3x"></i>
            <div class="info">
                <h4>In Circulation</h4>
                <p><b id="total-circulation-text">{{ money($moneyInCirculation) }}</b></p>
                <small>Collected − profit (approx.)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="widget-small danger coloured-icon">
            <i class="icon fa fa-shopping-cart fa-3x"></i>
            <div class="info">
                <h4>{{ $activeShift ? 'Shift Orders' : 'Today Orders' }}</h4>
                <p>
                    <b id="total-orders-count">{{ $totalOrders }}</b> <small>Total</small> |
                    <b class="text-white" id="active-orders-count">{{ $activeOrders }}</b> <small>Open</small>
                </p>
                <small>Paid: <span id="served-orders-count">{{ $servedOrders }}</span></small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="tile p-3 mb-4" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-muted small font-weight-bold text-uppercase">{{ $activeShift ? 'Shift velocity' : 'Hourly velocity' }}</h6>
                <span class="badge badge-primary">Sales per hour</span>
            </div>
            <div class="velocity-chart-container flex-grow-1">
                <canvas id="velocityChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="tile p-3 mb-4" style="min-height: 250px; display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-muted small font-weight-bold text-uppercase">Sales mix</h6>
                <span class="badge badge-info">Products vs services</span>
            </div>
            <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                <div style="width: 180px; height: 180px;">
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
                <i class="fa fa-flash text-warning mr-2"></i> Real-time sales stream
            </h3>
            <div class="tile-body" id="live-feed-container" style="max-height: 600px; overflow-y: auto;">
                @include('live-sales.partials.feed_items', ['liveFeed' => $liveFeed])
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="tile mb-4">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-users text-primary mr-2"></i> Staff leaderboard</h3>
            <div class="tile-body">
                <ul class="list-group list-group-flush" id="staff-pulse-container">
                    @include('live-sales.partials.staff_items', ['staffPulse' => $staffPulse])
                </ul>
            </div>
        </div>
        <div class="tile">
            <h3 class="tile-title border-bottom pb-2"><i class="fa fa-star text-warning mr-2"></i> Trending now</h3>
            <div class="tile-body">
                <div class="mb-3">
                    <h6 class="text-muted small font-weight-bold mb-3">TOP PRODUCTS</h6>
                    @forelse($topProducts as $product)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="small font-weight-bold">{{ $product->name }}</span>
                        <span class="badge badge-pill badge-primary">{{ number_format($product->total_qty, 0) }}</span>
                    </div>
                    @empty
                    <p class="small text-muted mb-0">No product lines yet.</p>
                    @endforelse
                </div>
                <hr>
                <div>
                    <h6 class="text-muted small font-weight-bold mb-3">TOP SERVICES</h6>
                    @forelse($topServices as $service)
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="small">{{ $service->name }}</span>
                        <span class="badge badge-pill badge-info">{{ number_format($service->total_qty, 0) }}</span>
                    </div>
                    @empty
                    <p class="small text-muted mb-0">No service lines yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var velocityChart, categoryChart;
    var refreshInterval = 30000;
    var lastRefresh = Date.now();
    var feedUrl = @json(route('live-sales.index'));
    var filterQuery = @json($filterQuery ?? []);

    function initCharts() {
        var velCtx = document.getElementById('velocityChart').getContext('2d');
        velocityChart = new Chart(velCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, function (_, i) { return i + ':00'; }),
                datasets: [{
                    label: 'Sales',
                    data: @json(array_values($hourlyData)),
                    borderColor: '#940000',
                    backgroundColor: 'rgba(148, 0, 0, 0.05)',
                    borderWidth: 3,
                    pointRadius: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#666', font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { precision: 0 }, grid: { borderDash: [5, 5] } }
                }
            }
        });

        var catCtx = document.getElementById('categoryChart').getContext('2d');
        categoryChart = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: ['Products', 'Services'],
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
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
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
            document.getElementById('margin-text').innerText = 'Margin: ' + data.margin_percent + '%';

            document.getElementById('total-orders-count').innerText = data.pulse.total_orders;
            document.getElementById('active-orders-count').innerText = data.pulse.active_orders;
            document.getElementById('served-orders-count').innerText = data.pulse.served_orders;

            document.getElementById('live-feed-container').innerHTML = data.live_feed;
            document.getElementById('staff-pulse-container').innerHTML = data.staff_pulse;

            velocityChart.data.datasets[0].data = data.hourly_data;
            velocityChart.update('none');

            categoryChart.data.datasets[0].data = [data.category_mix.products, data.category_mix.services];
            categoryChart.update('none');

            document.getElementById('last-updated-text').innerText = 'Synced: ' + new Date().toLocaleTimeString();
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
