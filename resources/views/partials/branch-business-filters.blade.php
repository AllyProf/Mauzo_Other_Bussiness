@if($viewingAllBranches ?? false)
<div class="alert alert-light border py-2 mb-3">
  <i class="fa fa-building"></i>
  Viewing <strong>all branches</strong>. Switch branch in the header to filter by location.
</div>
@elseif(!empty($activeBranchName))
<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-map-marker"></i>
  Showing data for branch <strong>{{ $activeBranchName }}</strong> — switch branch in the header to change location.
</div>
@endif

@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2">Business:</span>
    <div class="business-type-tabs mb-2">
      <a href="{{ url()->current() . (count(request()->except('business_type')) ? '?' . http_build_query(request()->except('business_type')) : '') }}"
         class="business-type-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> All
      </a>
      @foreach($businessTypes as $type)
        <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('business_type'), ['business_type' => $type['key']])) }}"
           class="business-type-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
          <i class="fa {{ $type['icon'] ?? 'fa-store' }}"></i> {{ $type['label'] }}
        </a>
      @endforeach
    </div>
  </div>
  @if($activeBusinessType ?? false)
    <p class="small text-muted mb-0">
      Filtered to <strong>{{ $activeBusinessLabel ?? $activeBusinessType }}</strong>{{ ($filterNote ?? null) ? ' — '.$filterNote : '' }}.
    </p>
  @elseif(!empty($filterHint))
    <p class="small text-muted mb-0">{{ $filterHint }}</p>
  @endif
</div>
<style>
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
</style>
@endif
