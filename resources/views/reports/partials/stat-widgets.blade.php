{{-- Usage: @include('reports.partials.stat-widgets', ['widgets' => [...]] ) --}}
{{-- Each widget: icon, color (primary|success|warning|danger|info), label, value --}}
<div class="row mb-3">
  @foreach($widgets as $widget)
  <div class="col-md-3 col-sm-6">
    <div class="widget-small {{ $widget['color'] ?? 'primary' }} coloured-icon">
      <i class="icon fa {{ $widget['icon'] }} fa-3x"></i>
      <div class="info">
        <h4>{{ $widget['label'] }}</h4>
        <p><b>{!! $widget['value'] !!}</b></p>
      </div>
    </div>
  </div>
  @endforeach
</div>
