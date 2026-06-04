@extends('layouts.app')

@section('title', 'Registration Funnel')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-filter"></i> Registration Funnel</h1><p>Last {{ $days }} days</p></div></div>

<form method="GET" class="mb-3">
  <select name="days" class="form-control" style="max-width:200px" onchange="this.form.submit()">
    @foreach([7, 30, 90] as $d)
    <option value="{{ $d }}" {{ $days == $d ? 'selected' : '' }}>Last {{ $d }} days</option>
    @endforeach
  </select>
</form>

<div class="row mb-3">
  <div class="col-md-6"><div class="widget-small success coloured-icon"><i class="icon fa fa-percent fa-3x"></i><div class="info"><h4>Conversion</h4><p><b>{{ $summary['conversion_rate'] }}%</b> landing → submitted</p></div></div></div>
  <div class="col-md-6"><div class="widget-small info coloured-icon"><i class="icon fa fa-check fa-3x"></i><div class="info"><h4>Approval Rate</h4><p><b>{{ $summary['approval_rate'] }}%</b> submitted → approved</p></div></div></div>
</div>

<div class="tile">
  <div class="tile-body">
    @foreach($summary['steps'] as $step)
    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
      <span>{{ $step['label'] }}</span>
      <strong>{{ number_format($step['count']) }}</strong>
    </div>
    @endforeach
  </div>
</div>
@endsection
