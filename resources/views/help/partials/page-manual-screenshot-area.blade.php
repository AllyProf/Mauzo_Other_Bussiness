@php
  $screenshot = $screenshot ?? null;
  $screenshotPanels = $screenshot_panels ?? null;
@endphp
@if(!empty($screenshotPanels))
  @include('help.partials.page-manual-screenshots-stack', ['panels' => $screenshotPanels])
@elseif($screenshot)
  @include('help.partials.page-manual-screenshot', [
    'screenshot' => $screenshot,
    'callouts' => $callouts ?? [],
  ])
@endif
