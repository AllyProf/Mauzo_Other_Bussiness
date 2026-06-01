@if(!empty($targetProgress) && $targetProgress->count() > 0)
@once
@push('styles')
<style>
  .staff-goals-strip { margin-bottom: 12px; }
  .staff-goals-strip.is-inline { margin-bottom: 0; }
  .staff-goals-strip .goals-head {
    font-size: 12px; font-weight: 700; color: #495057; margin-bottom: 6px;
  }
  .staff-goals-strip.is-inline .goals-head { margin-bottom: 4px; font-size: 11px; }
  .staff-goals-strip .goals-row { display: flex; flex-wrap: wrap; gap: 8px; }
  .staff-goals-strip .goal-chip {
    flex: 0 1 280px; max-width: 100%; min-width: 220px;
    background: #fff; border: 1px solid #e3e6ea; border-left: 3px solid #940000;
    border-radius: 5px; padding: 6px 8px;
  }
  .staff-goals-strip.is-inline .goal-chip {
    flex: 1 1 200px; min-width: 180px; background: #f8f9fa;
  }
  .staff-goals-strip .goal-line1 {
    display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px;
  }
  .staff-goals-strip .goal-type {
    font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    color: #940000; background: rgba(148,0,0,0.08); padding: 1px 5px; border-radius: 3px;
  }
  .staff-goals-strip .goal-pct { font-size: 11px; font-weight: 700; color: #940000; }
  .staff-goals-strip .goal-scope {
    font-size: 10px; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 160px;
  }
  .staff-goals-strip .goal-bar { height: 5px; background: #eaecf4; border-radius: 5px; overflow: hidden; margin-bottom: 3px; }
  .staff-goals-strip .goal-bar > span { display: block; height: 5px; border-radius: 5px; }
  .staff-goals-strip .goal-line2 {
    display: flex; justify-content: space-between; gap: 4px; font-size: 10px; color: #6c757d;
  }
  .staff-goals-strip .goal-line2 strong { color: #212529; font-size: 10px; }
</style>
@endpush
@endonce
@php
  $targetColors = ['#940000', '#009688', '#1565C0', '#f6c23e'];
  $inline = $goalsInline ?? false;
@endphp
<div class="staff-goals-strip {{ $inline ? 'is-inline' : '' }}">
  @if(! $inline)
    <div class="goals-head"><i class="fa fa-bullseye"></i> My Sales Goals</div>
  @else
    <div class="goals-head mb-1"><i class="fa fa-bullseye"></i> My Goal</div>
  @endif
  <div class="goals-row">
    @foreach($targetProgress as $index => $row)
      @php $color = $targetColors[$index % count($targetColors)]; @endphp
      <div class="goal-chip" style="border-left-color:{{ $color }};">
        <div class="goal-line1">
          <div class="d-flex align-items-center gap-1" style="min-width:0;">
            <span class="goal-type">{{ $row['period_type'] }}</span>
            @if(!empty($row['scope_label']))
              <span class="goal-scope" title="{{ $row['scope_label'] }}">{{ $row['scope_label'] }}</span>
            @endif
          </div>
          <span class="goal-pct">{{ $row['progress'] }}%</span>
        </div>
        <div class="goal-bar"><span style="width:{{ $row['progress'] }}%;background:{{ $color }};"></span></div>
        <div class="goal-line2">
          <span><strong>{{ money($row['actual'], false) }}</strong> / {{ money($row['target']->target_amount, false) }}</span>
          <span>{{ $row['period_label'] }}</span>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endif
