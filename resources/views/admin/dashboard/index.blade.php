@extends('layouts.app')

@section('title', 'Platform Dashboard')

@section('content')
@php $m = $metrics; @endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-dashboard"></i> Platform Dashboard</h1>
    <p>Software owner overview — subscriptions, support, billing, and business health.</p>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-building fa-3x"></i><div class="info"><h4>Businesses</h4><p><b>{{ $m['active_businesses'] }}</b> active / {{ $m['total_businesses'] }}</p></div></div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-clock-o fa-3x"></i><div class="info"><h4>Expiring (7d)</h4><p><b>{{ $m['expiring_this_week'] }}</b></p></div></div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon"><i class="icon fa fa-ticket fa-3x"></i><div class="info"><h4>Open Tickets</h4><p><b>{{ $m['open_tickets'] }}</b>@if($m['unread_tickets']) <span class="badge badge-danger">{{ $m['unread_tickets'] }} new</span>@endif</p></div></div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>Outstanding</h4><p><b>TZS {{ number_format($m['outstanding_amount'], 0) }}</b></p></div></div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="tile">
      <h3 class="tile-title">Pending Registrations ({{ $m['pending_registrations'] }})</h3>
      <div class="tile-body table-responsive">
        <table class="table table-sm table-hover mb-0">
          @forelse($m['pending_businesses'] as $b)
          <tr><td>{{ $b->name }}</td><td><a href="{{ route('admin.businesses.edit', $b) }}" class="btn btn-xs btn-primary">Review</a></td></tr>
          @empty
          <tr><td class="text-muted">No pending registrations.</td></tr>
          @endforelse
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="tile">
      <h3 class="tile-title">Expiring Soon</h3>
      <div class="tile-body table-responsive">
        <table class="table table-sm table-hover mb-0">
          @forelse($m['expiring_businesses'] as $b)
          <tr><td>{{ $b->name }}</td><td>{{ $b->expiry_date?->format('M d, Y') }}</td></tr>
          @empty
          <tr><td class="text-muted">No businesses expiring this week.</td></tr>
          @endforelse
        </table>
      </div>
    </div>
  </div>
</div>

@if($m['at_risk']->isNotEmpty())
<div class="tile mt-3">
  <h3 class="tile-title">Businesses Needing Attention</h3>
  <div class="tile-body table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>{{ __('tables.columns.business') }}</th><th>Health</th><th>Last Login</th><th>Sales (30d)</th><th></th></tr></thead>
      <tbody>
        @foreach($m['at_risk'] as $row)
        <tr>
          <td>{{ $row['business']->name }}</td>
          <td><span class="badge badge-{{ $row['health']['class'] }}">{{ $row['health']['label'] }}</span></td>
          <td>{{ $row['last_login_at'] ? \Carbon\Carbon::parse($row['last_login_at'])->diffForHumans() : 'Never' }}</td>
          <td>{{ $row['sales_30_days'] }}</td>
          <td><a href="{{ route('admin.onboarding.show', $row['business']) }}" class="btn btn-xs btn-outline-secondary">Onboarding</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

<div class="row mt-2">
  <div class="col-md-4"><a href="{{ route('admin.payments.index') }}" class="btn btn-block btn-outline-primary"><i class="fa fa-money"></i> Payments</a></div>
  <div class="col-md-4"><a href="{{ route('admin.monitor.index') }}" class="btn btn-block btn-outline-primary"><i class="fa fa-heartbeat"></i> Usage Monitor</a></div>
  <div class="col-md-4"><a href="{{ route('admin.leads.index') }}" class="btn btn-block btn-outline-primary"><i class="fa fa-envelope"></i> Demo Leads ({{ $m['new_leads'] }})</a></div>
</div>
@endsection
