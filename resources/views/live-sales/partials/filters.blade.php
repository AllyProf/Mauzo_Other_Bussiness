@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2"><i class="fa fa-sitemap"></i> Business:</span>
    <div class="pulse-filter-tabs mb-2">
      <a href="{{ route('live-sales.index') }}"
         class="pulse-filter-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> All
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
  <p class="small text-muted mb-0">Filtered to <strong>{{ $activeBusinessLabel ?? $activeBusinessType }}</strong>.</p>
  @endif
</div>
<style>
  .pulse-filter-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; max-width: 100%; }
  .pulse-filter-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .pulse-filter-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .pulse-filter-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .pulse-filter-tab i { margin-right: 5px; }
</style>
@endif
