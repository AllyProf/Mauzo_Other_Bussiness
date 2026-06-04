@extends('layouts.app')

@section('title', 'Onboarding — '.$business->name)

@section('content')
<div class="app-title"><div><h1><i class="fa fa-check-square-o"></i> Onboarding Checklist</h1><p>{{ $business->name }} — {{ $progress }}% complete</p></div></div>

<div class="tile">
  <div class="tile-body">
    <div class="progress mb-4" style="height:24px"><div class="progress-bar bg-success" style="width:{{ $progress }}%">{{ $progress }}%</div></div>
    <ul class="list-group">
      @foreach($checklist as $key => $step)
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <span><i class="fa fa-{{ $step['done'] ? 'check-circle text-success' : 'circle-o text-muted' }} mr-2"></i> {{ $step['label'] }}</span>
        @if($step['done_at'])<small class="text-muted">{{ \Carbon\Carbon::parse($step['done_at'])->format('M d, Y') }}</small>@endif
      </li>
      @endforeach
    </ul>
    <a href="{{ route('admin.businesses.edit', $business) }}" class="btn btn-outline-secondary mt-3"><i class="fa fa-arrow-left"></i> Back to Business</a>
  </div>
</div>
@endsection
