@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <div class="business-type-tabs">
      @php
        $tabQuery = request()->query();
        unset($tabQuery['business_type']);
        $allUrl = request()->url() . ($tabQuery ? '?' . http_build_query($tabQuery) : '');
      @endphp
      <a href="{{ $allUrl }}"
         class="business-type-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-large"></i> All
      </a>
      @foreach($businessTypes as $type)
      @php
        $typeQuery = array_merge(request()->query(), ['business_type' => $type['key']]);
        $typeUrl = request()->url() . '?' . http_build_query($typeQuery);
      @endphp
      <a href="{{ $typeUrl }}"
         class="business-type-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
        <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
      </a>
      @endforeach
    </div>
  </div>
  @if(!empty($activeBusinessType))
  <p class="text-muted small mb-0 mt-2">
    Showing data for <strong>{{ $business->businessTypeLabel($activeBusinessType) }}</strong>.
    @if(($businessTypeNote ?? null))
      {{ $businessTypeNote }}
    @endif
  </p>
  @endif
</div>
@endif
