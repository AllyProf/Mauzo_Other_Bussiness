{{-- Usage: @include('reports.partials.chart-tile', ['title' => '...', 'id' => 'chartId', 'cols' => 12, 'fixedHeight' => 280]) --}}
<div class="col-12 col-md-{{ $cols ?? 12 }} mb-3{{ !empty($fixedHeight) ? ' d-flex' : '' }}">
  <div class="tile mb-0{{ !empty($fixedHeight) ? ' flex-fill d-flex flex-column' : '' }}">
    <h3 class="tile-title">{{ $title }}</h3>
    <div class="tile-body{{ !empty($fixedHeight) ? ' flex-fill d-flex flex-column' : '' }}">
      @if(!empty($legendItems))
      <div class="report-chart-legend">
        @foreach($legendItems as $item)
        <span class="report-legend-item">
          <span class="report-legend-mark report-legend-{{ $item['type'] ?? 'line' }}" style="--legend-color: {{ $item['color'] }};"></span>
          {{ $item['label'] }}
        </span>
        @endforeach
      </div>
      @endif
      @if(!empty($emptyText))
      <p class="text-muted text-center small py-5 mb-0">{{ $emptyText }}</p>
      @elseif(!empty($fixedHeight))
      <div class="report-chart-wrap" style="height: {{ (int) $fixedHeight }}px;">
        <canvas id="{{ $id }}"></canvas>
      </div>
      @else
      <div class="embed-responsive embed-responsive-16by9">
        <canvas class="embed-responsive-item" id="{{ $id }}"></canvas>
      </div>
      @endif
    </div>
  </div>
</div>
