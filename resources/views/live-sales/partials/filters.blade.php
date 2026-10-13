@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2"><i class="fa fa-sitemap"></i> {{ __('live_sales.filters.business') }}</span>
    <div class="pulse-filter-tabs mb-2">
      <a href="{{ route('live-sales.index') }}"
         class="pulse-filter-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> {{ __('live_sales.filters.all') }}
      </a>
      @foreach($businessTypes as $type)
      <a href="{{ route('live-sales.index', ['business_type' => $type['key']]) }}"
         class="pulse-filter-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
        <i class="fa {{ $type['icon'] ?? 'fa-store' }}"></i> {{ $type['label'] }}
      </a>
      @endforeach
    </div>
  </div>
  @if($activeBusinessType ?? false)
  <p class="small text-muted mb-0">{!! __('live_sales.filtered_to', ['label' => '<strong>'.e($activeBusinessLabel ?? $activeBusinessType).'</strong>']) !!}</p>
  @endif
</div>
@endif
