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
@endif
