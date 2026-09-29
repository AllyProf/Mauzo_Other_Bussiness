@php
  $flagSize = $size ?? 18;
  $flagHeight = round($flagSize * 2 / 3);
@endphp
@if($code === 'sw')
<svg class="locale-flag" width="{{ $flagSize }}" height="{{ $flagHeight }}" viewBox="0 0 72 48" aria-hidden="true">
  <path d="M0 0h72L0 48z" fill="#1eb53a"/>
  <path d="M72 0v48H0z" fill="#00a3dd"/>
  <path d="M0 48L72 0" stroke="#fcd116" stroke-width="19"/>
  <path d="M0 48L72 0" stroke="#000" stroke-width="12"/>
</svg>
@elseif($code === 'en')
<svg class="locale-flag" width="{{ $flagSize }}" height="{{ $flagHeight }}" viewBox="0 0 7410 3900" aria-hidden="true">
  <rect width="7410" height="3900" fill="#b22234"/>
  <path d="M0 450h7410M0 1050h7410M0 1650h7410M0 2250h7410M0 2850h7410M0 3450h7410" stroke="#fff" stroke-width="300"/>
  <rect width="2964" height="2100" fill="#3c3b6e"/>
  <g fill="#fff">
    @foreach([[247,210],[741,210],[1235,210],[1729,210],[2223,210],[2717,210],[494,420],[988,420],[1482,420],[1976,420],[2470,420],[247,630],[741,630],[1235,630],[1729,630],[2223,630],[2717,630],[494,840],[988,840],[1482,840],[1976,840],[2470,840],[247,1050],[741,1050],[1235,1050],[1729,1050],[2223,1050],[2717,1050],[494,1260],[988,1260],[1482,1260],[1976,1260],[2470,1260],[247,1470],[741,1470],[1235,1470],[1729,1470],[2223,1470],[2717,1470],[494,1680],[988,1680],[1482,1680],[1976,1680],[2470,1680],[247,1890],[741,1890],[1235,1890],[1729,1890],[2223,1890],[2717,1890]] as [$x, $y])
    <circle cx="{{ $x }}" cy="{{ $y }}" r="80"/>
    @endforeach
  </g>
</svg>
@else
<i class="fa fa-globe"></i>
@endif
