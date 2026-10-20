@php
  $wireframe = $wireframe ?? 'list';
  $wf = __('page_manual.wireframe');
  $screenshot = $screenshot ?? null;
  $screenshotPanels = $screenshot_panels ?? null;
@endphp
@if(!empty($screenshotPanels) || $screenshot)
  <div class="page-manual-wireframe page-manual-wireframe--screenshot @if(!empty($screenshotPanels)) page-manual-wireframe--screenshot-stack @endif">
    @include('help.partials.page-manual-screenshot-area', [
      'screenshot' => $screenshot,
      'screenshot_panels' => $screenshotPanels,
      'callouts' => $callouts ?? [],
    ])
  </div>
@else
<div class="page-manual-wireframe page-manual-wireframe--{{ $wireframe }}">
  <div class="pmwf-app-chrome">
    <span class="pmwf-app-chrome__burger" aria-hidden="true"><i class="fa fa-bars"></i></span>
    <span class="pmwf-app-chrome__title">{{ $wf['app_title'] ?? 'Your store' }}</span>
  </div>
  <div class="pmwf-app-body">
    @switch($wireframe)
      @case('categories')
        <div class="pmwf-inner-title"></div>
        <div class="pmwf-cards">
          <span class="pmwf-card pmwf-card--active"></span>
          <span class="pmwf-card"></span>
          <span class="pmwf-card"></span>
        </div>
        <div class="pmwf-labeled-btn pmwf-labeled-btn--block pmwf-labeled-btn--accent">{{ $wf['import'] ?? 'Import' }}</div>
        <div class="pmwf-data-table-wrap">
          @include('help.partials.page-manual-wireframe-table')
        </div>
        @break
      @case('settings')
        <div class="pmwf-tabs">
          <span class="pmwf-tab pmwf-tab--active"></span>
          <span class="pmwf-tab"></span>
          <span class="pmwf-tab"></span>
        </div>
        <div class="pmwf-form">
          <div class="pmwf-field pmwf-field--lg"></div>
          <div class="pmwf-field"></div>
          <div class="pmwf-field"></div>
        </div>
        <div class="pmwf-screen-footer-btns">
          <span class="pmwf-labeled-btn pmwf-labeled-btn--accent">{{ $wf['save'] ?? 'Save' }}</span>
        </div>
        @break
      @case('report')
        <div class="pmwf-inner-title"></div>
        <div class="pmwf-filters">
          <div class="pmwf-field pmwf-field--select"></div>
          <div class="pmwf-field pmwf-field--date"></div>
          <span class="pmwf-labeled-btn pmwf-labeled-btn--accent">{{ $wf['run'] ?? 'Run' }}</span>
        </div>
        <div class="pmwf-data-table-wrap">
          @include('help.partials.page-manual-wireframe-table')
        </div>
        @break
      @case('pos')
        <div class="pmwf-pos-search"></div>
        <div class="pmwf-pos-grid">
          @for ($i = 0; $i < 6; $i++)
            <span class="pmwf-pos-tile"></span>
          @endfor
        </div>
        <div class="pmwf-pos-cart">
          <div class="pmwf-table-row"></div>
          <div class="pmwf-table-row"></div>
          <div class="pmwf-screen-footer-btns pmwf-screen-footer-btns--pos">
            <span class="pmwf-labeled-btn pmwf-labeled-btn--accent">{{ $wf['pay'] ?? 'Pay' }}</span>
          </div>
        </div>
        @break
      @case('form')
        <div class="pmwf-inner-title"></div>
        <div class="pmwf-form pmwf-form--padded">
          <div class="pmwf-field pmwf-field--lg"></div>
          <div class="pmwf-field"></div>
          <div class="pmwf-field"></div>
        </div>
        <div class="pmwf-screen-footer-btns">
          <span class="pmwf-labeled-btn">{{ $wf['cancel'] ?? 'Cancel' }}</span>
          <span class="pmwf-labeled-btn pmwf-labeled-btn--accent">{{ $wf['save'] ?? 'Save' }}</span>
        </div>
        @break
      @case('receiving_form')
        <div class="pmwf-receiving-layout">
          <div class="pmwf-receiving-main">
            <div class="pmwf-receiving-fields">
              <span class="pmwf-labeled-field">{{ $wf['supplier'] ?? 'Supplier' }}</span>
              <span class="pmwf-labeled-field pmwf-labeled-field--sm">{{ $wf['run'] ?? 'Date' }}</span>
              <span class="pmwf-labeled-field">{{ $wf['category'] ?? 'Category' }}</span>
            </div>
            <div class="pmwf-receiving-search">
              <span class="pmwf-labeled-btn pmwf-labeled-btn--search pmwf-labeled-btn--block">{{ $wf['search_item'] ?? 'Search item' }}</span>
            </div>
            <table class="pmwf-data-table pmwf-data-table--receiving" aria-hidden="true">
              <thead>
                <tr>
                  <th>{{ $wf['col_product'] ?? 'Product' }}</th>
                  <th>{{ $wf['col_qty'] ?? 'Qty' }}</th>
                  <th>{{ $wf['col_cost'] ?? 'Cost' }}</th>
                </tr>
              </thead>
              <tbody>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
              </tbody>
            </table>
          </div>
          <div class="pmwf-receiving-aside">
            <div class="pmwf-receiving-summary"></div>
            <span class="pmwf-labeled-btn pmwf-labeled-btn--accent pmwf-labeled-btn--block">{{ $wf['post_receipt'] ?? 'Post receipt' }}</span>
          </div>
        </div>
        @break
      @case('dashboard')
        <div class="pmwf-dash-cards">
          <span class="pmwf-dash-card"></span>
          <span class="pmwf-dash-card"></span>
        </div>
        <div class="pmwf-dash-links">
          @for ($i = 0; $i < 4; $i++)
            <div class="pmwf-dash-link"></div>
          @endfor
        </div>
        @break
      @default
        <div class="pmwf-inner-title"></div>
        <div class="pmwf-screen-toolbar">
          <span class="pmwf-labeled-btn pmwf-labeled-btn--search">{{ $wf['search'] ?? 'Search' }}</span>
          <span class="pmwf-labeled-btn pmwf-labeled-btn--accent pmwf-labeled-btn--add">{{ $wf['add'] ?? 'Add' }}</span>
        </div>
        <div class="pmwf-data-table-wrap">
          @include('help.partials.page-manual-wireframe-table')
        </div>
        <div class="pmwf-screen-footer-btns">
          <span class="pmwf-labeled-btn">{{ $wf['secondary_action'] ?? 'Action' }}</span>
          <span class="pmwf-labeled-btn pmwf-labeled-btn--accent">{{ $wf['primary_action'] ?? 'Submit' }}</span>
        </div>
    @endswitch
  </div>
  @foreach($callouts ?? [] as $pin)
    @if(isset($pin['n'], $pin['x'], $pin['y']))
      <span class="page-manual-callout" style="left: {{ (float) $pin['x'] }}%; top: {{ (float) $pin['y'] }}%;">{{ $pin['n'] }}</span>
    @endif
  @endforeach
</div>
@endif
