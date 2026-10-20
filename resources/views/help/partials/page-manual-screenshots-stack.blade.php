@php
  $panels = $panels ?? [];
@endphp
<div class="page-manual-screenshots-stack">
  @foreach($panels as $panel)
    @include('help.partials.page-manual-screenshot', [
      'screenshot' => $panel['screenshot'] ?? null,
      'callouts' => $panel['callouts'] ?? [],
    ])
  @endforeach
</div>
