@push('styles')
<style>
  #revenueTrendChart { max-height: 250px; }
  .product-bar-row { margin-bottom: 12px; }
  .product-bar-label {
    font-size: 14px; font-weight: 500; color: #2c3e50; margin-bottom: 6px;
    display: flex; justify-content: space-between; align-items: center;
  }
  .product-bar-track { height: 10px; background: #eaecf4; border-radius: 10px; }
  .product-bar-fill { height: 10px; background: #940000; border-radius: 10px; transition: width 1s ease; }
  .widget-small {
    height: 100px; margin-bottom: 20px; border-radius: 8px; overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }
  .widget-small .icon { width: 65px; line-height: 100px; font-size: 35px; }
  .widget-small .info {
    padding: 10px 15px; display: flex; flex-direction: column; justify-content: center;
  }
  .widget-small .info h4 {
    text-transform: uppercase; font-size: 13px; margin-bottom: 5px; font-weight: 600;
  }
  .widget-small .info p { margin-bottom: 1px; font-size: 18px; }
  .widget-small .info small { display: block; margin-top: 2px; }
  .owner-empty { text-align: center; color: #999; padding: 30px 15px; }
  .owner-empty i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: 0.5; }
  .pulse { animation: ownerPulse 2s infinite; }
  @keyframes ownerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
  }
</style>
@endpush

{{-- KPI Row --}}
<div class="row">
  <div class="col-md-3 col-sm-6">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Today Revenue</h4>
        <p><b>{{ money($todayRevenue) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>Month {{ now()->format('M Y') }}</h4>
        <p><b>{{ money($monthRevenue) }}</b></p>
        <small class="text-white" style="opacity:.85;">{{ number_format($monthOrders) }} orders · {{ money($monthCollected, false) }} collected</small>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-warning fa-3x"></i>
      <div class="info">
        <h4>Stock Alerts</h4>
        <p><b>{{ number_format($pendingShortages) }}</b></p>
        <small class="text-white" style="opacity:.85;">shortages · {{ number_format($lowStockCount) }} low stock</small>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-shopping-bag fa-3x"></i>
      <div class="info">
        <h4>Month Purchases</h4>
        <p><b>{{ money($monthlyPurchaseCost) }}</b></p>
        <small class="text-white" style="opacity:.85;">stock received</small>
      </div>
    </div>
  </div>
</div>

{{-- Sales target progress --}}
<div class="row">
  @if($targetProgress->count() > 0)
    @php $targetColors = ['#940000', '#009688', '#1565C0', '#f6c23e', '#6f42c1', '#e65100']; @endphp
    @foreach($targetProgress->take(4) as $index => $row)
      @php $color = $targetColors[$index % count($targetColors)]; @endphp
      <div class="col-md-6 mb-4">
        <div class="tile pb-2">
          <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
              <h6 class="text-muted small font-weight-bold mb-1">
                <i class="fa fa-bullseye mr-1"></i> {{ strtoupper($row['period_type']) }} GOAL
              </h6>
              <div class="small text-muted">{{ $row['scope_label'] }}</div>
            </div>
            <span class="badge badge-primary">{{ $row['progress'] }}%</span>
          </div>
          <div class="product-bar-track mt-2">
            <div class="product-bar-fill" style="width: {{ $row['progress'] }}%; background-color: {{ $color }} !important;"></div>
          </div>
          <div class="d-flex justify-content-between mt-1 small text-muted">
            <span>{{ money($row['actual']) }}</span>
            <span>Target: {{ money($row['target']->target_amount) }}</span>
          </div>
          <div class="small text-muted mt-1">{{ $row['period_label'] }}</div>
        </div>
      </div>
    @endforeach
  @else
    <div class="col-md-12 mb-4">
      <div class="tile text-center py-4">
        <i class="fa fa-bullseye fa-2x text-muted mb-2"></i>
        <p class="mb-2 text-muted">No sales targets set for the current day, week, or month.</p>
        <a href="{{ route('sales-targets.index') }}" class="btn btn-primary" style="background:#940000;border-color:#940000;">
          <i class="fa fa-plus"></i> Set Sales Targets
        </a>
      </div>
    </div>
  @endif
</div>

{{-- Charts --}}
<div class="row">
  <div class="col-md-8 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-area-chart"></i> Revenue — Last 7 Days</h3>
      <div class="tile-body">
        <canvas id="revenueTrendChart" style="max-height: 300px;"></canvas>
        @if(collect($revenueTrend)->sum('revenue') <= 0)
          <div class="owner-empty"><i class="fa fa-bar-chart"></i> No revenue data yet</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-pie-chart"></i> Category Sales</h3>
      <div class="tile-body text-center">
        <div style="position:relative;height:200px;width:100%;display:flex;justify-content:center;align-items:center;margin-bottom:15px;">
          <canvas id="categoryDistributionChart"></canvas>
        </div>
        @if($categoryDistribution->count() > 0)
          <ul class="list-group list-group-flush text-left" style="font-size:13px;">
            @foreach($categoryDistribution as $cat)
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1" style="border:none;border-bottom:1px solid #f0f0f0;">
              <span class="text-truncate" style="max-width:60%;">
                <i class="fa fa-circle mr-2" style="font-size:8px;color:#940000;"></i>
                {{ $cat['category'] ?? 'Uncategorized' }}
              </span>
              <span class="badge badge-pill badge-primary">{{ money($cat['revenue']) }}</span>
            </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted text-center mt-3">No sales data this month.</p>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Top products & staff --}}
<div class="row">
  <div class="col-md-8 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-star"></i> Top Products This Month</h3>
      <div class="tile-body">
        @if($topProducts->count() > 0)
          @php $maxQty = max(1, (float) $topProducts->max('qty')); @endphp
          <div class="row">
            @foreach($topProducts as $tp)
              @php $pct = round(((float) $tp['qty'] / $maxQty) * 100); @endphp
              <div class="col-md-6 mb-3">
                <div class="product-bar-label">
                  <span class="text-truncate pr-3" title="{{ $tp['name'] }}">{{ $tp['name'] }}</span>
                  <span style="color:#940000;font-weight:bold;">{{ number_format($tp['qty']) }} <small>sold</small></span>
                </div>
                <div class="product-bar-track">
                  <div class="product-bar-fill" style="width: {{ $pct }}%;"></div>
                </div>
                <div class="small text-muted mt-1">{{ money($tp['revenue']) }} · {{ $tp['category'] ?? '' }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="owner-empty"><i class="fa fa-star-o"></i> No sales data this month</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-4">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title"><i class="fa fa-users"></i> Top Staff This Month</h3>
      <div class="tile-body">
        @if($topStaff->count() > 0)
          <ul class="list-group list-group-flush">
            @foreach($topStaff as $staff)
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center" style="font-size:13px;">
              <div>
                <div class="font-weight-bold">{{ $staff['name'] }}</div>
                <small class="text-muted">{{ $staff['orders_count'] }} orders</small>
              </div>
              <div class="text-right">
                <div class="text-success font-weight-bold">{{ money($staff['total_revenue']) }}</div>
                @if($multiBusiness && !empty($staff['department_revenues']) && $staff['department_revenues']->count() > 1)
                  <div class="text-muted" style="font-size:11px;">
                    @foreach($staff['department_revenues']->take(2) as $dr)
                      {{ $dr['label'] }}: {{ money($dr['revenue'], false) }}@if(! $loop->last) · @endif
                    @endforeach
                  </div>
                @else
                  <div class="text-muted" style="font-size:11px;">Collected: {{ money($staff['collected'], false) }}</div>
                @endif
              </div>
            </li>
            @endforeach
          </ul>
        @else
          <div class="owner-empty"><i class="fa fa-user-o"></i> No staff sales this month</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Quick links --}}
<div class="row mt-2">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-bolt"></i> Quick Links</h3>
      <div class="tile-body">
        <div class="row">
          @can('process_sales')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('sales.create') }}" class="btn btn-primary btn-block p-3 text-center shadow-sm" style="background:#940000;border:none;">
              <i class="fa fa-shopping-cart fa-2x mb-2"></i><br>STORE / POS
            </a>
          </div>
          @endcan
          @can('manage_business_settings')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('sales-targets.index') }}" class="btn btn-outline-primary btn-block p-3 text-center" style="border-color:#940000;color:#940000;">
              <i class="fa fa-bullseye fa-2x mb-2"></i><br>SALES TARGETS
            </a>
          </div>
          @endcan
          @can('view_reports')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('reports.daily-sales') }}" class="btn btn-outline-primary btn-block p-3 text-center" style="border-color:#940000;color:#940000;">
              <i class="fa fa-line-chart fa-2x mb-2"></i><br>DAILY SALES
            </a>
          </div>
          @endcan
          @can('receive_stock')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('receivings.index') }}" class="btn btn-outline-success btn-block p-3 text-center">
              <i class="fa fa-truck fa-2x mb-2"></i><br>RECEIVING
            </a>
          </div>
          @endcan
          @can('view_inventory')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('items.stock') }}" class="btn btn-outline-info btn-block p-3 text-center">
              <i class="fa fa-cubes fa-2x mb-2"></i><br>ITEM STOCK
            </a>
          </div>
          @endcan
          @canany(['verify_stock_shortages', 'view_reports'])
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('stock-shortages.index') }}" class="btn btn-outline-warning btn-block p-3 text-center">
              <i class="fa fa-warning fa-2x mb-2"></i><br>STOCK SHORTAGES
              @if($pendingShortages > 0)
                <span class="badge badge-danger ml-1">{{ $pendingShortages }}</span>
              @endif
            </a>
          </div>
          @endcanany
          @canany(['manage_debts', 'collect_payments'])
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('debts.index') }}" class="btn btn-outline-danger btn-block p-3 text-center">
              <i class="fa fa-credit-card fa-2x mb-2"></i><br>DEBT MANAGEMENT
            </a>
          </div>
          @endcanany
          @canany(['view_reports', 'finalize_reports'])
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('owner-reports.index') }}" class="btn btn-outline-dark btn-block p-3 text-center">
              <i class="fa fa-file-text-o fa-2x mb-2"></i><br>MASTER SHEET
            </a>
          </div>
          @endcanany
          @can('view_reports')
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('reports.profit') }}" class="btn btn-outline-secondary btn-block p-3 text-center">
              <i class="fa fa-money fa-2x mb-2"></i><br>PROFIT REPORT
            </a>
          </div>
          @endcan
          <div class="col-md-3 col-sm-6 mb-3">
            <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-block p-3 text-center">
              <i class="fa fa-gears fa-2x mb-2"></i><br>BUSINESS SETTINGS
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  const trendData = @json($revenueTrend);
  const businessTypes = @json($businessTypes);
  const multiBusiness = @json($multiBusiness);
  const deptColors = ['rgba(148,0,0,0.85)', 'rgba(0,150,136,0.85)', 'rgba(21,101,192,0.85)', 'rgba(246,194,62,0.85)', 'rgba(111,66,193,0.85)'];

  const labels = [];
  const ordersSeries = [];

  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().slice(0, 10);
    labels.push(d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
    const match = trendData.find(r => r.date === dateStr);
    ordersSeries.push(match ? parseInt(match.orders) : 0);
  }

  const ctx = document.getElementById('revenueTrendChart');
  if (ctx) {
    const datasets = [];

    if (multiBusiness && businessTypes.length > 0) {
      businessTypes.forEach(function(type, index) {
        const data = [];
        for (let i = 6; i >= 0; i--) {
          const d = new Date();
          d.setDate(d.getDate() - i);
          const dateStr = d.toISOString().slice(0, 10);
          const match = trendData.find(r => r.date === dateStr);
          data.push(match && match.departments ? parseFloat(match.departments[type.key] || 0) : 0);
        }
        datasets.push({
          label: type.label + ' (TZS)',
          data: data,
          backgroundColor: deptColors[index % deptColors.length],
          borderRadius: 4,
          stack: 'revenue',
          yAxisID: 'y',
        });
      });
    } else {
      const data = [];
      for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const dateStr = d.toISOString().slice(0, 10);
        const match = trendData.find(r => r.date === dateStr);
        data.push(match ? parseFloat(match.revenue) : 0);
      }
      datasets.push({
        label: 'Revenue (TZS)',
        data: data,
        backgroundColor: 'rgba(148,0,0,0.85)',
        borderColor: '#6b0000',
        borderWidth: 1,
        borderRadius: 4,
        stack: 'revenue',
        yAxisID: 'y',
      });
    }

    datasets.push({
      type: 'line',
      label: 'Orders',
      data: ordersSeries,
      borderColor: '#7B1FA2',
      backgroundColor: 'rgba(123,31,162,0.1)',
      borderWidth: 2,
      pointRadius: 4,
      pointBackgroundColor: '#7B1FA2',
      tension: 0.4,
      fill: true,
      yAxisID: 'y1',
    });

    new Chart(ctx, {
      type: 'bar',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', labels: { font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: function(c) {
                if (c.dataset.label === 'Orders') return ' ' + c.parsed.y + ' orders';
                return ' TZS ' + c.parsed.y.toLocaleString();
              }
            }
          }
        },
        scales: {
          x: { stacked: true },
          y: {
            type: 'linear', position: 'left', stacked: true,
            ticks: { callback: v => 'TZS ' + (v >= 1000 ? Math.round(v/1000) + 'K' : v), font: { size: 10 } },
            grid: { color: 'rgba(0,0,0,0.04)' }
          },
          y1: {
            type: 'linear', position: 'right',
            ticks: { font: { size: 10 } },
            grid: { drawOnChartArea: false }
          }
        },
        animation: false
      }
    });
  }

  const distData = @json($categoryDistribution->values());
  const distCtx = document.getElementById('categoryDistributionChart');
  if (distCtx && distData.length > 0) {
    new Chart(distCtx, {
      type: 'doughnut',
      data: {
        labels: distData.map(d => d.category || 'Uncategorized'),
        datasets: [{
          data: distData.map(d => parseFloat(d.qty)),
          backgroundColor: ['#940000','#009688','#1565C0','#f6c23e','#6f42c1','#e65100','#28a745'],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(c) { return ' ' + c.parsed + ' units sold'; }
            }
          }
        },
        cutout: '70%',
        animation: false
      }
    });
  }
})();
</script>
@endpush
