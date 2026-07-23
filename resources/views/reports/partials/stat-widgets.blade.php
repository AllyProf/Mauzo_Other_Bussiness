{{-- Usage: @include('reports.partials.stat-widgets', ['widgets' => [...]] ) --}}
{{-- Each widget: icon, color (primary|success|warning|danger|info), label, value --}}
@php
  $widgets = collect($widgets ?? [])->values();
  $count = $widgets->count();
  $colClass = match (true) {
      $count <= 1 => 'col-12',
      $count === 2 => 'col-12 col-sm-6',
      $count === 3 => 'col-12 col-sm-6 col-lg-4',
      default => 'col-6 col-md-3',
  };
@endphp
<div class="row report-stat-widgets mb-3">
  @foreach($widgets as $widget)
  <div class="{{ $colClass }} report-stat-col">
    <div class="widget-small {{ $widget['color'] ?? 'primary' }} coloured-icon">
      <i class="icon fa {{ $widget['icon'] }} fa-3x" aria-hidden="true"></i>
      <div class="info">
        <h4>{{ $widget['label'] }}</h4>
        <p><b>{!! $widget['value'] !!}</b></p>
      </div>
    </div>
  </div>
  @endforeach
</div>
