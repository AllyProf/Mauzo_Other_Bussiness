@extends('layouts.app')

@section('title', ($isUnassignedStaffDashboard ?? false) ? 'Account Setup Required - SpareParts POS' : (($isSalesOfficerDashboard ?? false) ? 'Sales Dashboard - SpareParts POS' : (($isOwnerDashboard ?? false) ? 'Business Dashboard - SpareParts POS' : 'Dashboard - SpareParts POS')))

@section('content')
<div class="app-title">
  <div>
      <h1>
        <i class="fa fa-{{ ($isSalesOfficerDashboard ?? false) || ($isOwnerDashboard ?? false) ? 'tachometer' : 'dashboard' }}"></i>
        @if($isUnassignedStaffDashboard ?? false)
          Account Setup Required
        @elseif($isSalesOfficerDashboard ?? false)
          Sales Dashboard
        @elseif($isOwnerDashboard ?? false)
          Business Dashboard
        @else
          Dashboard
        @endif
      </h1>
    <p>
        @if(Auth::user()->role == 'super_admin')
            Platform Overview (Software Owner)
        @elseif($isUnassignedStaffDashboard ?? false)
            Hi {{ Auth::user()->name }} — your account needs a role before you can use the system.
        @elseif($isSalesOfficerDashboard ?? false)
            Welcome back, {{ Auth::user()->name }}!
            @if(!empty($activeBranchLabel))
              <span class="badge badge-light ml-1"><i class="fa fa-map-marker"></i> {{ $activeBranchLabel }}</span>
            @endif
        @elseif($isOwnerDashboard ?? false)
            Welcome back, <strong>{{ Auth::user()->name }}</strong> &mdash; {{ now()->format('l, F j, Y') }}
            @if(!empty($activeBranchLabel))
              <span class="badge badge-light ml-1"><i class="fa fa-map-marker"></i> {{ $activeBranchLabel }}</span>
            @endif
        @else
            Overview of {{ Auth::user()->business?->name ?? 'Spare Parts' }} Management
            @if(!empty($canSwitchBranch))
              <span class="badge badge-light ml-1"><i class="fa fa-map-marker"></i> {{ $activeBranchLabel ?? 'Branch' }}</span>
            @endif
        @endif
    </p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    @if($isSalesOfficerDashboard ?? false)
      <li class="breadcrumb-item">Sales Dashboard</li>
    @elseif($isOwnerDashboard ?? false)
      <li class="breadcrumb-item">Business Dashboard</li>
    @endif
  </ul>
</div>

@if($isUnassignedStaffDashboard ?? false)
  @include('home.unassigned-staff')
@elseif($isSalesOfficerDashboard ?? false)
  @include('home.sales-officer')
@elseif($isOwnerDashboard ?? false)
  @include('home.owner')
@else
@php
    $totalBusinesses = $totalBusinesses ?? \App\Models\Business::count();
    $activeBusinesses = $activeBusinesses ?? \App\Models\Business::where('is_active', true)->count();
    $expiringThisWeek = $expiringThisWeek ?? \App\Models\Business::where('is_active', true)
        ->whereNotNull('expiry_date')
        ->whereDate('expiry_date', '<=', \Carbon\Carbon::now()->addDays(7))
        ->whereDate('expiry_date', '>=', \Carbon\Carbon::now())
        ->count();
    $openTickets = $openTickets ?? \App\Models\Ticket::where('status', 'open')->count();
@endphp

<div class="row">
  @if(Auth::user()->role == 'super_admin')
    <div class="col-md-6 col-lg-3">
        <div class="widget-small primary coloured-icon"><i class="icon fa fa-building fa-3x"></i>
          <div class="info">
            <h4>Total Businesses</h4>
            <p><b>{{ $totalBusinesses }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small success coloured-icon"><i class="icon fa fa-check-circle fa-3x"></i>
          <div class="info">
            <h4>Active Businesses</h4>
            <p><b>{{ $activeBusinesses }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small warning coloured-icon"><i class="icon fa fa-clock-o fa-3x"></i>
          <div class="info">
            <h4>Expiring This Week</h4>
            <p><b>{{ $expiringThisWeek }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small danger coloured-icon"><i class="icon fa fa-ticket fa-3x"></i>
          <div class="info">
            <h4>Open Tickets</h4>
            <p><b>{{ $openTickets }}</b></p>
          </div>
        </div>
      </div>
  @else
    <div class="col-md-6 col-lg-3">
        <div class="widget-small primary coloured-icon"><i class="icon fa fa-users fa-3x"></i>
          <div class="info">
            <h4>Staff</h4>
            <p><b>{{ $staffCount ?? \App\Models\User::where('business_id', Auth::user()->business_id)->count() }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small info coloured-icon"><i class="icon fa fa-cubes fa-3x"></i>
          <div class="info">
            <h4>Total Items</h4>
            <p><b>{{ $itemsCount ?? \App\Models\Item::where('business_id', Auth::user()->business_id)->count() }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small warning coloured-icon"><i class="icon fa fa-warning fa-3x"></i>
          <div class="info">
            <h4>Low Stock</h4>
            <p><b>0</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small danger coloured-icon"><i class="icon fa fa-money fa-3x"></i>
          <div class="info">
            <h4>Today's Sales</h4>
            <p><b>{{ money($todaySalesTotal ?? 0) }}</b></p>
          </div>
        </div>
      </div>
  @endif
</div>

@if(Auth::user()->role != 'super_admin')
@include('home.partials.my-sales-targets')
@endif

@if(Auth::user()->role == 'super_admin')
@php
    $expiringBusinesses = $expiringBusinesses ?? \App\Models\Business::with('plan')
        ->where('is_active', true)
        ->whereNotNull('expiry_date')
        ->whereDate('expiry_date', '<=', \Carbon\Carbon::now()->addDays(7))
        ->whereDate('expiry_date', '>=', \Carbon\Carbon::now())
        ->get();
    $allBusinesses = $allBusinesses ?? \App\Models\Business::with('plan')->latest()->get();
@endphp

{{-- Pending Registrations --}}
@if(($pendingRegistrations ?? collect())->count() > 0)
<div class="row">
    <div class="col-md-12">
        <div class="tile" style="border-left: 4px solid #f39c12;">
            <h3 class="tile-title text-warning"><i class="fa fa-hourglass-half"></i> Pending Registrations ({{ $pendingRegistrations->count() }})</h3>
            <div class="tile-body">
                <table class="table table-hover table-sm">
                    <thead><tr><th>Business</th><th>Owner</th><th>Phone</th><th>Location</th><th>Business Type</th><th>Registered</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach($pendingRegistrations as $biz)
                        <tr>
                            <td><strong>{{ $biz->name }}</strong></td>
                            <td>{{ $biz->contact_person ?? $biz->ownerUser?->name ?? '—' }}</td>
                            <td>{{ $biz->phone ?? '—' }}</td>
                            <td>{{ $biz->region ?? '—' }}<br><small class="text-muted">{{ $biz->district ?? '' }}</small></td>
                            <td>{{ collect($biz->categoryBusinessTypesList())->first()['label'] ?? '—' }}</td>
                            <td>{{ $biz->created_at->format('M d, Y h:i A') }}</td>
                            <td>
                                <a href="{{ route('admin.businesses.index') }}" class="btn btn-sm btn-warning">Review</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Expiry Alerts --}}
@if($expiringBusinesses->count() > 0)
<div class="row">
    <div class="col-md-12">
        <div class="tile" style="border-left: 4px solid #f39c12;">
            <h3 class="tile-title"><i class="fa fa-exclamation-triangle text-warning mr-2"></i>⚠️ Subscription Expiry Alerts (Next 7 Days)</h3>
            <div class="tile-body">
                <table class="table table-hover table-sm">
                    <thead><tr><th>Business</th><th>Plan</th><th>Expiry Date</th><th>Days Left</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach($expiringBusinesses as $biz)
                        @php $daysLeft = \Carbon\Carbon::now()->diffInDays($biz->expiry_date); @endphp
                        <tr>
                            <td><strong>{{ $biz->name }}</strong></td>
                            <td>{{ $biz->plan->name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($biz->expiry_date)->format('M d, Y') }}</td>
                            <td><span class="badge badge-{{ $daysLeft <= 2 ? 'danger' : 'warning' }}">{{ $daysLeft }} day(s)</span></td>
                            <td><a href="{{ route('admin.businesses.edit', $biz->id) }}" class="btn btn-sm btn-primary">Renew Now</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

{{-- All Businesses Overview --}}
<div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
            <h3 class="title">All Registered Businesses</h3>
            <p><a class="btn btn-primary icon-btn" href="{{ route('admin.businesses.create') }}"><i class="fa fa-plus"></i> Register New</a></p>
        </div>
        <div class="tile-body">
        <table class="table table-hover table-bordered table-sm">
          <thead style="background-color: #940000; color: white;">
            <tr>
              <th>Business Name</th>
              <th>Plan</th>
              <th>Expiry Date</th>
              <th>Status</th>
              <th>Joined</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($allBusinesses as $business)
                @php
                    $expired = $business->expiry_date && \Carbon\Carbon::parse($business->expiry_date)->isPast();
                    $expiringSoon = !$expired && $business->expiry_date && \Carbon\Carbon::parse($business->expiry_date)->diffInDays(now()) <= 7;
                @endphp
                <tr class="{{ !$business->is_active ? 'table-danger' : ($expiringSoon ? 'table-warning' : '') }}">
                    <td><strong>{{ $business->name }}</strong><br><small class="text-muted">{{ $business->email }}</small></td>
                    <td>{{ $business->plan->name ?? '—' }}</td>
                    <td>
                        @if($business->expiry_date)
                            {{ \Carbon\Carbon::parse($business->expiry_date)->format('M d, Y') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($business->pending_approval)
                            <span class="badge badge-warning">Pending Approval</span>
                        @elseif(!$business->is_active)
                            <span class="badge badge-danger">Suspended</span>
                        @elseif($expired)
                            <span class="badge badge-secondary">Expired</span>
                        @elseif($expiringSoon)
                            <span class="badge badge-warning">Expiring Soon</span>
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                    </td>
                    <td>{{ $business->created_at->format('M d, Y') }}</td>
                    <td class="text-center">
                        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-info btn-sm mr-1" title="Edit"><i class="fa fa-edit"></i></a>
                        <form action="{{ route('admin.businesses.toggle-status', $business->id) }}" method="POST" class="d-inline">
                            @csrf
                            @if($business->is_active)
                                <button type="submit" class="btn btn-danger btn-sm mr-1" title="Suspend" onclick="confirmAction(event, 'Suspend Business?', 'This will lock out all staff immediately!')"><i class="fa fa-ban"></i></button>
                            @else
                                <button type="submit" class="btn btn-success btn-sm mr-1" title="Activate" onclick="confirmAction(event, 'Activate Business?', 'This will restore full access.')"><i class="fa fa-check"></i></button>
                            @endif
                        </form>
                        <form action="{{ route('admin.impersonate', $business->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm" title="Login As" onclick="confirmAction(event, 'Impersonate Business?', 'You will be logged in as the owner.')"><i class="fa fa-user-secret"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
    </div>
</div>
  @else
  @php
    $dashboardAlerts = $dashboardAlerts ?? (Auth::user()->role === 'owner' && Auth::user()->business
      ? app(\App\Services\BusinessSettingsService::class)->dashboardAlerts(Auth::user()->business)
      : []);
  @endphp

  @if(!empty($dashboardAlerts))
  <div class="row mb-2">
    <div class="col-md-12">
      @foreach($dashboardAlerts as $alert)
      <div class="alert alert-{{ $alert['type'] }} d-flex justify-content-between align-items-center flex-wrap mb-2" style="border-left:4px solid;">
        <div>
          <strong><i class="fa {{ $alert['icon'] }}"></i> {{ $alert['title'] }}</strong>
          <span class="d-block small mb-0 mt-1">{{ $alert['message'] }}</span>
        </div>
        @if(!empty($alert['action_url']))
        <a href="{{ $alert['action_url'] }}" class="btn btn-sm btn-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'primary') }} mt-2 mt-md-0">{{ $alert['action_label'] ?? 'View' }}</a>
        @endif
      </div>
      @endforeach
    </div>
  </div>
  @endif

  <div class="row">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Monthly Sales Trend</h3>
      <div class="embed-responsive embed-responsive-16by9">
        <canvas class="embed-responsive-item" id="lineChartDemo"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Stock Status</h3>
      <div class="embed-responsive embed-responsive-16by9">
        <canvas class="embed-responsive-item" id="pieChartDemo"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Low Stock Alerts</h3>
      <table class="table table-hover table-bordered">
        <thead>
          <tr>
            <th>Item Name</th>
            <th>SKU</th>
            <th>Current Stock</th>
            <th>Min. Required</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="5" class="text-center">No low stock items found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
@endif
@endsection

@section('scripts')
    @if(!($isUnassignedStaffDashboard ?? false) && !($isSalesOfficerDashboard ?? false) && !($isOwnerDashboard ?? false) && Auth::user()->role != 'super_admin')
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/chart.js') }}"></script>
    <script type="text/javascript">
      var data = {
      	labels: ["Jan", "Feb", "Mar", "Apr", "May"],
      	datasets: [
      		{
      			label: "Sales",
      			fillColor: "rgba(220,220,220,0.2)",
      			strokeColor: "rgba(220,220,220,1)",
      			pointColor: "rgba(220,220,220,1)",
      			pointStrokeColor: "#fff",
      			pointHighlightFill: "#fff",
      			pointHighlightStroke: "rgba(220,220,220,1)",
      			data: [0, 0, 0, 0, 0]
      		}
      	]
      };
      var pdata = [
      	{
      		value: 100,
      		color: "#46BFBD",
      		highlight: "#5AD3D1",
      		label: "In Stock"
      	},
      	{
      		value: 0,
      		color:"#F7464A",
      		highlight: "#FF5A5E",
      		label: "Out of Stock"
      	}
      ]
      
      var ctxl = $("#lineChartDemo").get(0).getContext("2d");
      var lineChart = new Chart(ctxl).Line(data);
      
      var ctxp = $("#pieChartDemo").get(0).getContext("2d");
      var pieChart = new Chart(ctxp).Pie(pdata);
    </script>
    @endif
@endsection
