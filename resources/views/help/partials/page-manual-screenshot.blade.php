@php
  $screenshot = $screenshot ?? null;
  $callouts = $callouts ?? [];
@endphp
@if($screenshot)
<div class="page-manual-screenshot">
  <div class="page-manual-screenshot__frame">
    <img
      src="{{ asset($screenshot) }}"
      alt=""
      class="page-manual-screenshot__img"
      loading="lazy"
    >
    @foreach($callouts as $pin)
      @if(isset($pin['n'], $pin['x'], $pin['y']))
        @php
          $left = (float) ($pin['tx'] ?? $pin['x']);
          $top = (float) ($pin['ty'] ?? $pin['y']);
        @endphp
        <span
          class="page-manual-pin"
          style="left: {{ $left }}%; top: {{ $top }}%;"
          aria-hidden="true"
        ><span class="page-manual-pin__num">{{ $pin['n'] }}</span></span>
      @endif
    @endforeach
  </div>
</div>
@endif
